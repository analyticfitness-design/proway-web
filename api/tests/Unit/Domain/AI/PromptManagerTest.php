<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Domain\AI;

use PHPUnit\Framework\TestCase;
use ProWay\Domain\AI\PromptManager;

class PromptManagerTest extends TestCase
{
    private PromptManager $manager;

    protected function setUp(): void
    {
        $this->manager = new PromptManager();
    }

    public function test_get_quote_template_with_variables(): void
    {
        $result = $this->manager->get('quote', [
            'title'       => 'Video Corporativo',
            'description' => 'Video de 2 minutos',
            'client_name' => 'ACME S.A.',
        ]);

        $this->assertStringContainsString('Video Corporativo', $result);
        $this->assertStringContainsString('ACME S.A.', $result);
        $this->assertStringNotContainsString('{{title}}', $result);
        $this->assertStringNotContainsString('{{client_name}}', $result);
    }

    public function test_get_throws_on_unknown_template(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->get('unknown_template');
    }

    public function test_has_returns_true_for_known_keys(): void
    {
        foreach (['quote', 'script', 'content', 'chat'] as $key) {
            $this->assertTrue($this->manager->has($key));
        }
    }

    public function test_has_returns_false_for_unknown_key(): void
    {
        $this->assertFalse($this->manager->has('nonexistent'));
    }

    public function test_variables_without_replacement_leave_placeholder(): void
    {
        // When no variables passed, placeholders remain in template
        $result = $this->manager->get('quote', []);
        $this->assertStringContainsString('{{title}}', $result);
    }
}
