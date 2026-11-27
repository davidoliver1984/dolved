<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DifyMemoryPromptConfigurationTest extends TestCase
{
    public function test_correction_removes_only_the_duplicate_current_query_insertion(): void
    {
        $root = dirname(__DIR__, 4);
        $before = json_decode((string) file_get_contents(
            $root.'/deployment/dolved-dify/workflow-config/20261125/published-llm-node.before.json',
        ), true, flags: JSON_THROW_ON_ERROR);
        $corrected = json_decode((string) file_get_contents(
            $root.'/deployment/dolved-dify/workflow-config/20261125/published-llm-node.corrected.json',
        ), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('{{#sys.query#}}', $before['data']['prompt_template'][1]['text']);
        $this->assertSame('user', $before['data']['prompt_template'][1]['role']);
        $this->assertCount(1, $corrected['data']['prompt_template']);
        $this->assertSame('system', $corrected['data']['prompt_template'][0]['role']);
        $this->assertSame($before['data']['prompt_template'][0], $corrected['data']['prompt_template'][0]);
        $this->assertSame($before['data']['memory'], $corrected['data']['memory']);
        $this->assertSame($before['data']['context'], $corrected['data']['context']);
        $this->assertSame($before['data']['model'], $corrected['data']['model']);

        unset($before['data']['prompt_template'][1]);
        $before['data']['prompt_template'] = array_values($before['data']['prompt_template']);
        $this->assertSame($before, $corrected);
    }
}
