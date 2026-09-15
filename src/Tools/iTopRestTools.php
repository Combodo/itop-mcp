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
     * Quote the string to protect from user input breaking the JSON / OQL
     * Based on MySQL's real_escape_string logic
     * @param string $value
     * @return string
     */
    public static function quoteString(string $value)
    {
        $replacementMap = [
            "\0" => "\\0",
            "\n" => "\\n",
            "\r" => "\\r",
            "\t" => "\\t",
            chr(26) => "\\Z",
            chr(8) => "\\b",
            '"' => '\"',
            "'" => "\'",
            '_' => "\_",
            "%" => "\%",
            '\\' => '\\\\'
        ];
        
        return strtr($value, $replacementMap);
    }
}