<?php
namespace App\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Psr\Log\LoggerInterface;

class iTopClient implements iTopClientInterface
{
    public function __construct(
        private Security $security,
        private LoggerInterface $mcpLogger,
        private string $iTopUrl,
        private int $curlConnectTimeout,
        private int $curlTimeout,
        private bool $curlSslVerifyPeer
    )
    {
        
    }
    
    public function postJsonToItop(string $json): string
    {
        $token = $this->security->getUser()->getUserIdentifier();
        $postData = [
            'version' => '1.4',
            'auth_token' => $token,
            'json_data' => $json,
        ];
        $this->mcpLogger->debug("iTopClient, POSTing to URL: '".$this->getRestEndpointUrl()."'", ['json_data' => $json]);
        // Default options, can be overloaded/extended with the 4th parameter of this method, see above $aCurlOptions
        $options = [
            CURLOPT_RETURNTRANSFER => true,     // return the content of the request
            CURLOPT_HEADER         => false,    // don't return the headers in the output
            CURLOPT_FOLLOWLOCATION => true,     // follow redirects
            CURLOPT_ENCODING       => "",       // handle all encodings
            CURLOPT_USERAGENT      => "MCP-server", // who am i
            CURLOPT_AUTOREFERER    => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => $this->curlConnectTimeout,      // timeout on connect
            CURLOPT_TIMEOUT        => $this->curlTimeout,      // timeout on response
            CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
            CURLOPT_SSL_VERIFYPEER => $this->curlSslVerifyPeer,
            CURLOPT_POST           => count($postData),
            CURLOPT_POSTFIELDS     => http_build_query($postData),
        ];
        
        $ch = curl_init($this->getRestEndpointUrl());
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $iErr = curl_errno($ch);
        $sErrMsg = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
 
        if ($iErr !== 0) {
            $this->mcpLogger->error("Problem opening URL: '".$this->getRestEndpointUrl()."', $sErrMsg");
            throw new \Exception("Problem opening URL: '".$this->getRestEndpointUrl()."', $sErrMsg");
        }
        if ($info['http_code'] !== 200)
        {
            $this->mcpLogger->error("Problem opening URL: '".$this->getRestEndpointUrl()."', got a response code of {$info['http_code']}", ['body' => $response]);
            throw new \Exception("Problem opening URL: '".$this->getRestEndpointUrl()."', got a response code of {$info['http_code']}");
        }
        $this->mcpLogger->debug("iTopClient,response", ['response' => $response]);
        return $response;
    }
    
    public function canConnect(): bool
    {
        return (null !== $this->security->getUser());
    }
    
    public function getRestEndpointUrl(): string
    {
        $suffix = '/webservices/rest.php';
        if (strpos($this->iTopUrl, $suffix) === false) {
            return trim($this->iTopUrl, '/').$suffix;
        }
        return $this->iTopUrl;
    }
}
