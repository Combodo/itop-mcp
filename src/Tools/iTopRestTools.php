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
     * Quote the string to protect from user input breaking the OQL string literal
     * (between single quotes) it is inserted into.
     * iTop's OQL lexer only accepts two escape sequences inside a quoted literal, \' and \\,
     * which its parser then unescapes with stripslashes(): any other sequence, such as \_ or \%,
     * is a syntax error. The JSON layer needs nothing from this method, since the .json.twig
     * templates are rendered with Twig's 'js' escaping strategy.
     * @param string $value
     * @return string
     */
    public static function quoteString(string $value)
    {
        $replacementMap = [
            "'" => "\'",
            '\\' => '\\\\'
        ];

        return strtr($value, $replacementMap);
    }
}