<?php
declare(strict_types=1);

namespace App\Tools;

use Twig\Environment;
use App\Service\iTopClientInterface;
use Psr\Log\LoggerInterface;

abstract class iTopRestTools
{
    public function __construct(protected Environment $twig, protected iTopClientInterface $iTopClient, protected LoggerInterface $mcpLogger)
    {
        
    }

    protected function runToolFromTemplates(string $inputTemplateName, string $outputTemplateName, $parameters): string
    {

        try {
            $this->mcpLogger->debug("[runToolsFromTemplate]", ['template' => $inputTemplateName, 'parameters' => $parameters]);
            $json = $this->twig->render($inputTemplateName.'-input.json.twig', $parameters);
            $this->mcpLogger->debug("[runToolsFromTemplate] input json", ['json' => $json]);
            $output = $this->postJsonToItop($json);
            $this->mcpLogger->debug("[runToolsFromTemplate] output", ['output' => $output]);
            $result = $this->twig->render($outputTemplateName.'-output.toon.twig', ['json' => json_decode($output, true)]);
            $this->mcpLogger->debug("[runToolsFromTemplate] result", ['result' => $result]);     
        } catch (\Throwable $t) {
            $this->mcpLogger->error($t->getMessage(), ['line' => $t->getLine(), 'file' => $t->getFile()]);
            $result = $t->getMessage();
        }
        return $result;
    }
    
    protected function postJsonToItop(string $json): string
    {
        return $this->iTopClient->postJsonToItop($json);
    }

    /**
     * Removes the characters that would let a value break out of its OQL string literal:
     * quotes, the escape character, and control characters. Meant for exact-match values
     * (equality, IN lists) - a LIKE pattern also needs its wildcards (%, _) stripped,
     * which callers must handle themselves.
     *
     * Duplicated here (rather than shared) because this PR and #8 are independent,
     * isolated proposals - see this PR's discussion for why, and the intent to collapse
     * the duplication once both land.
     */
    protected function escapeOqlLiteral(string $value): string
    {
        $value = str_replace(["\\", "'", '"'], '', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        return trim($value);
    }
}