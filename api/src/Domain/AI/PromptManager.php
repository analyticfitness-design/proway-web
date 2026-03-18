<?php
declare(strict_types=1);

namespace ProWay\Domain\AI;

class PromptManager
{
    private array $templates;

    public function __construct()
    {
        $this->templates = [
            'quote' => "Eres un experto en producción de video. Genera una cotización profesional para:\nProyecto: {{title}}\nDescripción: {{description}}\nCliente: {{client_name}}\n\nIncluye: desglose de servicios, tiempos de entrega, precio estimado en COP.",

            'script' => "Eres un director creativo de video. Crea un guión para:\nTipo: {{type}}\nTema: {{topic}}\nDuración: {{duration}} segundos\nTono: {{tone}}\n\nIncluye: narración, indicaciones visuales y llamada a la acción.",

            'content' => "Eres un estratega de contenido para redes sociales. Crea contenido para:\nPlatforma: {{platform}}\nTema: {{topic}}\nObjetivo: {{goal}}\n\nIncluye: copy, hashtags relevantes y emojis apropiados.",

            'chat' => "Eres el asistente virtual de ProWay Lab, una agencia de producción de video profesional. Ayuda al cliente con sus consultas de forma amigable y profesional.\n\nCliente: {{client_name}}\nPlan: {{plan_type}}",
        ];
    }

    public function get(string $key, array $variables = []): string
    {
        $template = $this->templates[$key] ?? throw new \InvalidArgumentException("Unknown prompt template: $key");

        foreach ($variables as $var => $value) {
            $template = str_replace('{{' . $var . '}}', (string) $value, $template);
        }

        return $template;
    }

    public function has(string $key): bool
    {
        return isset($this->templates[$key]);
    }
}
