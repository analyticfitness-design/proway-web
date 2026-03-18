<?php
declare(strict_types=1);

namespace ProWay\Tests\Unit\Domain\AI;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ProWay\Domain\AI\AIService;
use ProWay\Domain\AI\PromptManager;
use PDO;

class AIServiceTest extends TestCase
{
    private PDO&MockObject           $pdo;
    private PromptManager&MockObject $prompts;
    private AIService                $service;

    protected function setUp(): void
    {
        $this->pdo     = $this->createMock(PDO::class);
        $this->prompts = $this->createMock(PromptManager::class);
        $this->service = new AIService($this->pdo, $this->prompts);
    }

    public function test_generate_quote_calls_prompt_manager_with_correct_variables(): void
    {
        $this->prompts->expects($this->once())
            ->method('get')
            ->with('quote', [
                'title'       => 'Video Promo',
                'description' => 'Video de 60 segundos',
                'client_name' => 'ACME',
            ])
            ->willReturn('Test prompt');

        // We expect an API call to fail (no real API key in tests) — catch the RuntimeException
        try {
            $this->service->generateQuote('Video Promo', 'Video de 60 segundos', 'ACME');
        } catch (\RuntimeException $e) {
            // Expected — no real API key in test environment
            $this->assertStringContainsString('AI API call failed', $e->getMessage());
        }
    }

    public function test_generate_script_calls_prompt_manager_with_correct_variables(): void
    {
        $this->prompts->expects($this->once())
            ->method('get')
            ->with('script', [
                'type'     => 'reel',
                'topic'    => 'fitness',
                'duration' => 30,
                'tone'     => 'energético',
            ])
            ->willReturn('Script prompt');

        try {
            $this->service->generateScript('reel', 'fitness', 30, 'energético');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('AI API call failed', $e->getMessage());
        }
    }

    public function test_prompt_manager_template_substitution(): void
    {
        $real = new PromptManager();
        $result = $real->get('script', [
            'type'     => 'testimonial',
            'topic'    => 'brand story',
            'duration' => 90,
            'tone'     => 'emocional',
        ]);

        $this->assertStringContainsString('testimonial', $result);
        $this->assertStringContainsString('brand story', $result);
        $this->assertStringNotContainsString('{{type}}', $result);
        $this->assertStringNotContainsString('{{duration}}', $result);
    }
}
