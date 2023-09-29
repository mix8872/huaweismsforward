<?php

namespace Classes;

use Exception;
use SimpleXMLElement;
use Spatie\ArrayToXml\ArrayToXml;
use GuzzleHttp\Client;

class ModemProcessor
{
    /**
     * Get request type
     *
     * @const string
     */
    public const TYPE_GET = 'get';

    /**
     * Post request type
     *
     * @const string
     */
    public const TYPE_POST = 'post';

    /**
     * Get session tokens path
     *
     * @const string
     */
    public const TOKEN_URL = 'webserver/SesTokInfo';

    /**
     * Get sms list path
     *
     * @const string
     */
    public const SMS_LIST_URL = 'sms/sms-list';

    /**
     * Mark sms as read path
     *
     * @const string
     */
    public const SMS_READ_URL = 'sms/set-read';

    /**
     * Base modem api url
     *
     * @attribute string
     */
    protected string $host = 'http://192.168.8.1/api';

    /**
     * Result request url
     *
     * @attribute string
     */
    protected string $url;

    /**
     * Current request type
     *
     * @attribute string
     */
    protected string $type = self::TYPE_GET;

    /**
     * Request headers array
     *
     * @attribute array
     */
    protected array $headers = [];

    public function __construct(string $host = '')
    {
        $host && $this->host = $host;
    }

    /**
     * Set tokens for modem request
     *
     * @return void
     * @throws Exception
     */
    protected function setTokens(): void
    {
        $this->path = self::TOKEN_URL;
        $tokensData = simplexml_load_string(file_get_contents("$this->host/$this->path"));
        if (!$tokensData->SesInfo || !$tokensData->TokInfo) {
            throw new Exception('Wrong response: ' . $tokensData);
        }

        $this->headers[] = "Cookie:$tokensData->SesInfo";
        $this->headers[] = "__RequestVerificationToken:$tokensData->TokInfo";
    }

    /**
     * Main sms process method
     *
     * @return void
     * @throws Exception
     */
    public function processSms(): void
    {
        $sms = $this->post(self::SMS_LIST_URL, [
            'PageIndex' => 1,
            'ReadCount' => 50,
            'BoxType' => 1,
            'SortType' => 0,
            'Ascending' => 0,
            'UnreadPreferred' => 1
        ]);

        if (!$sms->Messages) {
            die($sms->asXML());
        }

        $count = 0;
        foreach ($sms->Messages->Message as $message) {
            if ($message->Smstat != '0') {
                continue;
            }
            $count++;
            try {
                echo "New message from {$message->Phone} at {$message->Date}";
                $this->sendTgMsg((string)$message->Phone, (string)$message->Content, (string)$message->Date);
                $this->setRead((int)$message->Index);
                echo " - \e[0;32mSuccess\e[0m" . PHP_EOL;
            } catch (Exception $e) {
                echo " - \e[0;31mFail\e[0m" . PHP_EOL;
            }
        }

        if (!$count) {
            echo "\e[0;32mNo new messages\e[0m" . PHP_EOL;
        }
    }

    /**
     * Mark sms as read
     *
     * @param int $index
     * @return void
     * @throws Exception
     */
    protected function setRead(int $index): void
    {
        $this->post(self::SMS_READ_URL, [
            'Index' => $index,
        ]);
    }

    /**
     * Send POST request to modem
     *
     * @param string $path
     * @param array $data
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function post(string $path, array $data = []): SimpleXMLElement
    {
        $this->setTokens();
        $this->type = self::TYPE_POST;
        $path = trim($path, "/ ");
        $this->url = "$this->host/$path";
        return $this->request(ArrayToXml::convert($data, 'request'));
    }

    /**
     * Send GET request to modem
     *
     * @param string $path
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function get(string $path): SimpleXMLElement
    {
        $this->setTokens();
        $path = trim($path, "/ ");
        $this->url = "$this->host/$path";
        return $this->request();
    }

    /**
     * Forward sms to the telegram
     *
     * @param string $from
     * @param string $message
     * @param string $date
     * @return SimpleXMLElement
     */
    protected function sendTgMsg(string $from, string $message, string $date): SimpleXMLElement
    {
        $this->headers[] = 'Content-Type: application/json';
        $this->url = "https://api.telegram.org/bot{$_SERVER['TG_TOKEN']}/sendMessage";
        $this->type = self::TYPE_POST;

        $text = "*$from* ($date)\n  ";
        $text .= "$message";

        $text = str_replace(['+', '(', ')', '-'], ['\+', '\(', '\)', '\-'], $text);

        return $this->request(json_encode([
            'chat_id' => (int)$_SERVER['CHAT_ID'],
            'text' => $text,
            'parse_mode' => 'MarkdownV2'
        ]));
    }

    /**
     * Make request
     *
     * @param $data
     * @return mixed
     */
    protected function request($data): mixed
    {
        $ch = curl_init($this->url);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        if ($this->type == self::TYPE_POST) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        curl_close($ch);
        unset($ch);
        return str_starts_with($result, '<?xml') ? simplexml_load_string($result) : $result;
    }
}
