from uuid import uuid4

from app.evaluation.live_hybrid_retrieval import (
    EvaluationChunk,
    _candidate_funnels,
    _candidate_lineage,
)
from app.evaluation.models import EvaluationCase, EvidenceUnit, QuestionVariant
from app.reranking.models import RerankedCandidate
from app.retrieval.models import RetrievalCandidate, RetrievalSide
from app.retrieval.retriever import RetrievalStageSnapshot


def test_candidate_lineage_and_compare_funnels_retain_exact_observed_stages() -> None:
    chunk_id = uuid4()
    document_id = uuid4()
    workspace_generation_id = uuid4()
    embedding_generation_id = uuid4()
    sparse_generation_id = uuid4()
    chunk = EvaluationChunk(
        candidate_id="evidence.current",
        chunk_id=chunk_id,
        document_id=document_id,
        document_family_id="family.policy",
        document_version_id="policy.v2",
        text="The current procedure requires two staff members.",
    )
    dense = RetrievalCandidate(
        chunk_id=chunk_id,
        document_id=document_id,
        workspace_corpus_generation_id=workspace_generation_id,
        embedding_space_generation_id=embedding_generation_id,
        sparse_space_generation_id=sparse_generation_id,
        score=0.81,
        rank=2,
        side=RetrievalSide.PRIMARY,
    )
    sparse = dense.model_copy(
        update={"score": 4.2, "rank": 1, "retrieval_method": "sparse"}
    )
    fused = dense.model_copy(
        update={
            "score": 0.0325,
            "rank": 1,
            "retrieval_method": "hybrid",
            "dense_score": dense.score,
            "dense_rank": dense.rank,
            "sparse_score": sparse.score,
            "sparse_rank": sparse.rank,
        }
    )
    reranked = RerankedCandidate(
        chunk_id=chunk_id,
        side=RetrievalSide.PRIMARY,
        score=0.72,
        rank=1,
    )
    snapshot = RetrievalStageSnapshot(
        side=RetrievalSide.PRIMARY,
        dense_candidates=(dense,),
        sparse_candidates=(sparse,),
        fused_candidates=(fused,),
    )
    case = EvaluationCase(
        case_id="case.current",
        variants=(QuestionVariant(variant_id="direct", question="What is current?"),),
        slices=("CURRENT",),
        expected_outcome="EVIDENCE_FOUND",
        evidence_units=(
            EvidenceUnit(
                evidence_id="evidence.current",
                document_family_id="family.policy",
                document_version_id="policy.v2",
                source_path="documents/policy-v2.md",
                canonical_excerpts=(
                    "The current procedure requires two staff members.",
                ),
            ),
        ),
    )
    key = (RetrievalSide.PRIMARY, chunk_id)

    lineage = _candidate_lineage(
        case=case,
        chunks={chunk_id: chunk},
        snapshots=(snapshot,),
        reranked=(reranked,),
        evidence_threshold=0.5,
        final_keys={key},
        dense_only=False,
    )
    funnels = _candidate_funnels(
        snapshots=(snapshot,),
        sent_to_reranker=(fused,),
        threshold_survivors=(reranked,),
        final_keys={key},
        dense_only=False,
    )

    assert len(lineage) == 1
    assert lineage[0].dense_rank == 2
    assert lineage[0].sparse_rank == 1
    assert lineage[0].fused_rank == 1
    assert lineage[0].reranker_rank == 1
    assert lineage[0].passed_evidence_threshold is True
    assert lineage[0].included_in_final_evidence is True
    assert lineage[0].covered_evidence_unit_ids == ("evidence.current",)
    assert funnels[0].model_dump() == {
        "side": "PRIMARY",
        "dense_candidate_count": 1,
        "sparse_candidate_count": 1,
        "unique_post_fusion_count": 1,
        "candidates_sent_to_reranker": 1,
        "candidates_surviving_threshold": 1,
        "final_evidence_count": 1,
    }
