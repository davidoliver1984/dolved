<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GenerationRun;
use App\Models\QuestionReview;
use App\Models\QuestionReviewSubject;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceQuestionReviewPolicy;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class QuestionReviewController extends Controller
{
    private const REVIEW_STATUSES = [
        'unreviewed', 'answered_adequately', 'incomplete', 'unsupported_answer', 'missing_evidence',
        'retrieval_issue', 'question_outside_scope', 'technical_failure',
    ];

    private const ISSUE_TYPES = [
        'not_yet_established', 'missing_content', 'incomplete_or_unclear_content', 'retrieval_or_applicability_issue',
        'answer_generation_issue', 'technical_failure', 'outside_scope', 'not_a_gap',
    ];

    private const WORKFLOW_STATES = ['needs_review', 'confirmed', 'action_planned', 'awaiting_verification', 'resolved', 'not_a_gap'];

    public function policy(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        Gate::authorize('viewAdministration', $workspace);
        $policy = $workspace->questionReviewPolicy;

        return response()->json(['data' => $this->policyPayload($policy, Gate::allows('manageQuestionReview', $workspace), Gate::allows('reviewQuestions', $workspace))]);
    }

    public function updatePolicy(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        Gate::authorize('manageQuestionReview', $workspace);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'include_retrospective' => ['required', 'boolean'],
            'retrospective_days' => ['required', 'integer', 'min:0', 'max:30'],
            'privacy_confirmation' => ['required', 'accepted'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $now = CarbonImmutable::now('UTC');

        $policy = DB::transaction(function () use ($workspace, $actor, $validated, $now): WorkspaceQuestionReviewPolicy {
            $policy = WorkspaceQuestionReviewPolicy::query()->lockForUpdate()->firstOrNew(['workspace_id' => $workspace->id]);
            $enabling = (bool) $validated['enabled'];
            $retrospectiveDays = $enabling && (bool) $validated['include_retrospective'] ? (int) $validated['retrospective_days'] : 0;
            if ($enabling) {
                $policy->fill([
                    'enabled' => true,
                    'policy_version' => 'question-review-v1',
                    'effective_from' => $now,
                    'review_from' => $now->subDays($retrospectiveDays),
                    'retrospective_days' => $retrospectiveDays,
                    'enabled_by_user_id' => $actor->id,
                    'disabled_at' => null,
                    'disabled_by_user_id' => null,
                ]);
            } else {
                $policy->fill(['enabled' => false, 'disabled_at' => $now, 'disabled_by_user_id' => $actor->id]);
            }
            $policy->save();
            $this->audit($workspace, $actor, $enabling ? 'policy_enabled' : 'policy_disabled', null, null, [
                'policy_version' => 'question-review-v1',
                'retrospective_days' => $retrospectiveDays,
            ]);

            return $policy;
        });

        return response()->json(['data' => $this->policyPayload($policy, true, (bool) $policy->enabled)]);
    }

    public function index(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        $policy = $this->authoriseReview($workspace);
        $validated = $request->validate([
            'review_status' => ['nullable', Rule::in(self::REVIEW_STATUSES)],
            'workflow_state' => ['nullable', Rule::in(self::WORKFLOW_STATES)],
            'subject' => ['nullable', 'uuid'],
        ]);
        $query = GenerationRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('created_at', '>=', $policy->review_from)
            ->with(['conversation.selectedLocation', 'questionReview.subjects', 'answerFeedback'])
            ->latest('created_at');
        if (isset($validated['review_status'])) {
            $query->whereHas('questionReview', fn (Builder $review) => $review->where('review_status', $validated['review_status']));
        }
        if (isset($validated['workflow_state'])) {
            $query->whereHas('questionReview', fn (Builder $review) => $review->where('workflow_state', $validated['workflow_state']));
        }
        if (isset($validated['subject'])) {
            $query->whereHas('questionReview.subjects', fn (Builder $subject) => $subject->where('public_id', $validated['subject'])->where('workspace_id', $workspace->id));
        }
        $runs = $query->paginate(50);

        return response()->json(['data' => collect($runs->items())->map(fn (GenerationRun $run): array => $this->summaryPayload($run))->all(), 'meta' => [
            'total' => $runs->total(),
            'review_from' => $policy->review_from?->toIso8601String(),
            'content_hidden_by_default' => true,
        ]]);
    }

    public function show(Request $request, string $workspacePublicId, string $runPublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        $policy = $this->authoriseReview($workspace);
        /** @var User $actor */
        $actor = $request->user();
        $purpose = (string) $request->validate(['purpose' => ['required', 'string', 'min:5', 'max:255']])['purpose'];
        $run = $this->reviewableRun($workspace, $policy, $runPublicId)->load([
            'conversation.selectedLocation', 'userMessage', 'assistantMessage', 'generatedAnswer.retrievedSources', 'questionReview.subjects', 'answerFeedback',
        ]);
        $this->audit($workspace, $actor, 'content_viewed', $run, $run->questionReview, ['purpose' => $purpose], $purpose);

        return response()->json(['data' => array_merge($this->summaryPayload($run), [
            'question' => $run->userMessage->display_text,
            'answer' => $run->assistantMessage?->display_text,
            'sources' => $run->generatedAnswer?->retrievedSources->map(fn ($source): array => [
                'public_id' => $source->public_id,
                'rank' => $source->rank,
                'display_name' => $source->display_name,
                'document_id' => $source->document?->public_id,
            ])->values()->all() ?? [],
            'answer_feedback' => $this->feedbackPayload($run),
            'notice' => 'Restricted application content. This access was recorded in the workspace audit trail.',
        ])]);
    }

    public function update(Request $request, string $workspacePublicId, string $runPublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        $policy = $this->authoriseReview($workspace);
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate([
            'review_status' => ['required', Rule::in(self::REVIEW_STATUSES)],
            'issue_type' => ['nullable', Rule::in(self::ISSUE_TYPES)],
            'workflow_state' => ['required', Rule::in(self::WORKFLOW_STATES)],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'owner_user_id' => ['nullable', 'uuid'],
            'review_notes' => ['nullable', 'string', 'max:4000'],
            'linked_document_public_id' => ['nullable', 'uuid'],
            'subject_ids' => ['present', 'array', 'max:8'],
            'subject_ids.*' => ['uuid'],
        ]);
        $run = $this->reviewableRun($workspace, $policy, $runPublicId);
        $ownerId = null;
        if ($validated['owner_user_id'] ?? null) {
            $ownerId = $workspace->memberships()->whereHas('user', fn (Builder $query) => $query->where('public_id', $validated['owner_user_id']))->value('user_id');
            abort_if($ownerId === null, 422, 'The review owner must belong to this workspace.');
        }
        $subjects = QuestionReviewSubject::query()->where('workspace_id', $workspace->id)->whereIn('public_id', $validated['subject_ids'])->get();
        abort_if($subjects->count() !== count(array_unique($validated['subject_ids'])), 422, 'One or more subjects are unavailable.');

        $review = DB::transaction(function () use ($workspace, $run, $actor, $validated, $ownerId, $subjects): QuestionReview {
            $resolved = in_array($validated['workflow_state'], ['resolved', 'not_a_gap'], true);
            $review = QuestionReview::query()->firstOrNew(['generation_run_id' => $run->id]);
            if (! $review->exists) {
                $review->fill(['public_id' => (string) Str::uuid(), 'workspace_id' => $workspace->id]);
            }
            $review->fill([
                'review_status' => $validated['review_status'],
                'issue_type' => $validated['issue_type'] ?? null,
                'workflow_state' => $validated['workflow_state'],
                'priority' => $validated['priority'],
                'owner_user_id' => $ownerId,
                'review_notes' => $validated['review_notes'] ?? null,
                'linked_document_public_id' => $validated['linked_document_public_id'] ?? null,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'resolved_by_user_id' => $resolved ? $actor->id : null,
                'resolved_at' => $resolved ? now() : null,
            ])->save();
            $review->subjects()->sync($subjects->modelKeys());
            $this->audit($workspace, $actor, 'review_updated', $run, $review, [
                'review_status' => $review->review_status,
                'issue_type' => $review->issue_type,
                'workflow_state' => $review->workflow_state,
                'subject_count' => $subjects->count(),
            ]);

            return $review->load('subjects');
        });

        return response()->json(['data' => $this->reviewPayload($review)]);
    }

    public function subjects(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        $this->authoriseReview($workspace);

        return response()->json(['data' => $workspace->questionReviewSubjects()->orderBy('name')->get()->map(fn (QuestionReviewSubject $subject): array => $this->subjectPayload($subject))->all()]);
    }

    public function storeSubject(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        $this->authoriseReview($workspace);
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validate(['name' => ['required', 'string', 'min:2', 'max:120'], 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])]]);
        $subject = QuestionReviewSubject::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'name' => trim($validated['name']),
            'priority' => $validated['priority'],
            'status' => 'active',
        ]);
        $this->audit($workspace, $actor, 'subject_created', null, null, ['subject_id' => $subject->public_id]);

        return response()->json(['data' => $this->subjectPayload($subject)], 201);
    }

    public function gaps(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        $this->authoriseReview($workspace);
        $subjects = QuestionReviewSubject::query()->where('workspace_id', $workspace->id)->where('status', 'active')
            ->withCount([
                'reviews as question_count',
                'reviews as reviewed_count' => fn (Builder $query) => $query->where('review_status', '!=', 'unreviewed'),
                'reviews as unresolved_count' => fn (Builder $query) => $query->whereNotIn('workflow_state', ['resolved', 'not_a_gap']),
                'reviews as confirmed_gap_count' => fn (Builder $query) => $query->whereIn('issue_type', ['missing_content', 'incomplete_or_unclear_content'])->whereNotIn('workflow_state', ['not_a_gap']),
            ])->orderByDesc('confirmed_gap_count')->orderByDesc('question_count')->orderBy('name')->get();

        return response()->json(['data' => $subjects->map(fn (QuestionReviewSubject $subject): array => array_merge($this->subjectPayload($subject), [
            'question_count' => $subject->question_count,
            'reviewed_count' => $subject->reviewed_count,
            'unresolved_count' => $subject->unresolved_count,
            'confirmed_gap_count' => $subject->confirmed_gap_count,
        ]))->all(), 'meta' => ['description' => 'Human-reviewed records only; this is not a complete audit of the knowledge base.']]);
    }

    public function potentialGaps(Request $request, string $workspacePublicId, FindWorkspaceForUser $workspaces): JsonResponse
    {
        $workspace = $this->workspace($request, $workspacePublicId, $workspaces);
        $policy = $this->authoriseReview($workspace);
        $subjects = QuestionReviewSubject::query()->where('workspace_id', $workspace->id)->where('status', 'active')
            ->orderByRaw('LENGTH(name) DESC')->get();
        $runs = GenerationRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('created_at', '>=', $policy->review_from)
            ->with(['userMessage', 'answerFeedback', 'questionReview', 'generatedAnswer.retrievedSources.document.family.category'])
            ->latest('created_at')
            ->limit(500)
            ->get();

        $groups = [];
        foreach ($runs as $run) {
            $noAnswer = $run->status->value === 'retrieval_no_answer'
                && in_array($run->retrieval_outcome?->value, ['no_eligible_evidence', 'no_retrieval_candidates', 'insufficient_evidence'], true);
            $negativeFeedback = $run->answerFeedback?->rating === 'not_helpful';
            $reviewedMissingEvidence = in_array($run->questionReview?->issue_type, ['missing_content', 'incomplete_or_unclear_content'], true);
            if (! $noAnswer && ! $negativeFeedback && ! $reviewedMissingEvidence) {
                continue;
            }

            $subject = $this->suggestedSubject($run, $subjects);
            $key = mb_strtolower($subject);
            $groups[$key] ??= [
                'suggested_subject' => $subject,
                'candidate_count' => 0,
                'no_answer_count' => 0,
                'negative_feedback_count' => 0,
                'reviewed_missing_evidence_count' => 0,
                'latest_signal_at' => null,
            ];
            $groups[$key]['candidate_count']++;
            $groups[$key]['no_answer_count'] += $noAnswer ? 1 : 0;
            $groups[$key]['negative_feedback_count'] += $negativeFeedback ? 1 : 0;
            $groups[$key]['reviewed_missing_evidence_count'] += $reviewedMissingEvidence ? 1 : 0;
            $groups[$key]['latest_signal_at'] ??= $run->created_at->toIso8601String();
        }

        $data = collect($groups)->map(function (array $group): array {
            $signalTypes = collect([
                $group['no_answer_count'],
                $group['negative_feedback_count'],
                $group['reviewed_missing_evidence_count'],
            ])->filter(fn (int $count): bool => $count > 0)->count();

            return [...$group, 'signal_strength' => $group['candidate_count'] >= 2 || $signalTypes >= 2 ? 'repeated' : 'early'];
        })->sortByDesc('candidate_count')->values()->all();

        return response()->json(['data' => $data, 'meta' => [
            'description' => 'Automatically derived candidate signals. These are suggestions, not confirmed content gaps.',
            'classification' => 'Registered subject-name matching, then retrieved document category; otherwise Unclassified.',
            'technical_failures_excluded' => true,
            'run_limit' => 500,
        ]]);
    }

    private function workspace(Request $request, string $publicId, FindWorkspaceForUser $workspaces): Workspace
    {
        /** @var User $user */
        $user = $request->user();

        return $workspaces->handle($user, $publicId)->workspace;
    }

    private function authoriseReview(Workspace $workspace): WorkspaceQuestionReviewPolicy
    {
        Gate::authorize('reviewQuestions', $workspace);

        return $workspace->questionReviewPolicy()->where('enabled', true)->firstOrFail();
    }

    private function reviewableRun(Workspace $workspace, WorkspaceQuestionReviewPolicy $policy, string $runPublicId): GenerationRun
    {
        return GenerationRun::query()->where('workspace_id', $workspace->id)->where('public_id', $runPublicId)
            ->where('created_at', '>=', $policy->review_from)->firstOrFail();
    }

    private function policyPayload(?WorkspaceQuestionReviewPolicy $policy, bool $canManage, bool $canReview): array
    {
        return [
            'enabled' => (bool) ($policy?->enabled ?? false),
            'policy_version' => $policy?->policy_version ?? 'question-review-v1',
            'effective_from' => $policy?->effective_from?->toIso8601String(),
            'review_from' => $policy?->review_from?->toIso8601String(),
            'retrospective_days' => (int) ($policy?->retrospective_days ?? 0),
            'can_manage' => $canManage,
            'can_review' => $canReview,
            'retrospective_default' => 'off',
        ];
    }

    private function summaryPayload(GenerationRun $run): array
    {
        return [
            'run_id' => $run->public_id,
            'occurred_at' => $run->created_at->toIso8601String(),
            'run_status' => $run->status->value,
            'retrieval_outcome' => $run->retrieval_outcome?->value,
            'site_context' => $run->conversation->selectedLocation?->name ?? 'Organisation-wide',
            'answer_feedback' => $this->feedbackPayload($run),
            'review' => $run->questionReview ? $this->reviewPayload($run->questionReview) : [
                'review_status' => 'unreviewed', 'issue_type' => null, 'workflow_state' => 'needs_review',
                'priority' => 'normal', 'review_notes' => null, 'subjects' => [],
            ],
        ];
    }

    private function reviewPayload(QuestionReview $review): array
    {
        return [
            'public_id' => $review->public_id,
            'review_status' => $review->review_status,
            'issue_type' => $review->issue_type,
            'workflow_state' => $review->workflow_state,
            'priority' => $review->priority,
            'review_notes' => $review->review_notes,
            'linked_document_public_id' => $review->linked_document_public_id,
            'reviewed_at' => $review->reviewed_at?->toIso8601String(),
            'subjects' => $review->relationLoaded('subjects') ? $review->subjects->map(fn (QuestionReviewSubject $subject): array => $this->subjectPayload($subject))->all() : [],
        ];
    }

    private function subjectPayload(QuestionReviewSubject $subject): array
    {
        return ['public_id' => $subject->public_id, 'name' => $subject->name, 'status' => $subject->status, 'priority' => $subject->priority];
    }

    private function feedbackPayload(GenerationRun $run): ?array
    {
        if (! $run->relationLoaded('answerFeedback') || $run->answerFeedback === null) {
            return null;
        }

        return [
            'rating' => $run->answerFeedback->rating,
            'reason_code' => $run->answerFeedback->reason_code,
            'comment' => $run->answerFeedback->comment,
            'submitted_at' => $run->answerFeedback->submitted_at?->toIso8601String(),
        ];
    }

    /** @param Collection<int, QuestionReviewSubject> $subjects */
    private function suggestedSubject(GenerationRun $run, Collection $subjects): string
    {
        $question = mb_strtolower((string) $run->userMessage?->display_text);
        foreach ($subjects as $subject) {
            if (str_contains($question, mb_strtolower($subject->name))) {
                return $subject->name;
            }
        }

        $category = $run->generatedAnswer?->retrievedSources
            ?->map(fn ($source) => $source->document?->family?->category?->name)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return is_string($category) && $category !== '' ? $category : 'Unclassified';
    }

    private function audit(Workspace $workspace, User $actor, string $eventType, ?GenerationRun $run = null, ?QuestionReview $review = null, array $metadata = [], ?string $purpose = null): void
    {
        DB::table('question_review_audit_events')->insert([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'actor_user_id' => $actor->id,
            'generation_run_id' => $run?->id,
            'question_review_id' => $review?->id,
            'event_type' => $eventType,
            'purpose' => $purpose,
            'safe_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
