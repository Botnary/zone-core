<?php
/**
 * Created by IntelliJ IDEA.
 * User: Prog1
 * Date: 11/28/2014
 * Time: 10:49 AM
 */

namespace Zone\Core\Component\SMS\Services;


use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class NexmoService implements ISmsService
{
    const NX_USERNAME = '550e5d2c'; // Enter your username here
    const NX_PASSWORD = '878b577f'; // Enter your password here
    const NX_SERVER = 'http://rest.nexmo.com/sms/json';

    private $_logger;

    function __construct()
    {
        $this->_logger = new Logger('Gestion Application');
        $this->_logger->pushHandler(new ErrorLogHandler());
    }

    /**
     * @return mixed
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    /**
     * Prepare and send new text message.
     */
    private function sendRequest($post)
    {
        $to_nexmo = curl_init(self::NX_SERVER);
        curl_setopt($to_nexmo, CURLOPT_POST, true);
        curl_setopt($to_nexmo, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($to_nexmo, CURLOPT_POSTFIELDS, $post);
        $from_nexmo = curl_exec($to_nexmo);
        curl_close($to_nexmo);
        return str_replace('-', '_', $from_nexmo);
    }

    function sendText(SmsMessage $message)
    {
        $from = $message->fromPhone;
        if (!is_numeric($from))
            $from = utf8_encode($from); //Must be UTF-8 Encoded if not a continuous number
        $text = utf8_encode($message->message); //Must be UTF-8 Encoded
        $from = urlencode($from); // URL Encode
        $text = urlencode($text); // URL Encode
        $post = 'username=' . self::NX_USERNAME . '&password=' . self::NX_PASSWORD . '&from=' . $from . '&to=' . $message->toPhone . '&text=' . $text;
        $request = json_decode($this->sendRequest($post));
        $this->getLogger()->addDebug('SMS: send status', $request);
        $sent = true;
        foreach ($request->messages as $message) {
            if ($message->status == 1) {
                $this->getLogger()->addDebug('SMS: You have exceeded the submission capacity allowed on this account, please back-off and retry', $request);
                $sent = false;
            } elseif ($message->status > 1) {
                $sent = false;
            }
        }
        return $sent;
    }

    function sendBinary(BinaryMessage $message)
    {
        //Binary messages must be hex encoded
        $body = bin2hex($message->message); //Must be hex encoded binary
        $udh = bin2hex($message->udh); //Must be hex encoded binary
        $post = 'username=' . self::NX_USERNAME . '&password=' . self::NX_PASSWORD . '&from=' . $message->fromPhone . '&to=' . $message->toPhone . '&type=binary&body=' . $body . '&udh=' . $udh;
        $request = json_decode($this->sendRequest($post));
        $sent = true;
        foreach ($request->messages as $message) {
            if ($message->status == 1) {
                $this->getLogger()->addDebug('SMS: You have exceeded the submission capacity allowed on this account, please back-off and retry', $request);
                $sent = false;
            } elseif ($message->status > 1) {
                $sent = false;
            }
        }
        return $sent;
    }

    function sendWapPush(WapMessage $message)
    {
        //WAP Push title and URL must be UTF-8 Encoded
        $title = utf8_encode($message->message); //Must be UTF-8 Encoded
        $url = utf8_encode($message->url); //Must be UTF-8 Encoded
        $post = 'username=' . self::NX_USERNAME . '&password=' . self::NX_PASSWORD . '&from=' . $message->fromPhone . '&to=' . $message->toPhone . '&type=wappush&url=' . $url . '&title=' . $title . '&validity=' . $message->validity;
        $request = json_decode($this->sendRequest($post));
        $sent = true;
        foreach ($request->messages as $message) {
            if ($message->status == 1) {
                $this->getLogger()->addDebug('SMS: You have exceeded the submission capacity allowed on this account, please back-off and retry', $request);
                $sent = false;
            } elseif ($message->status > 1) {
                $sent = false;
            }
        }
        return $sent;
    }
}