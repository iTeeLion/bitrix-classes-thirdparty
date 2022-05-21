<?php


namespace App/Smsc;


class Smsc
{

    protected $login;
    protected $password;

    public function __construct($login, $password)
    {
        $this->login = $login;
        $this->password = $password;
    }

    private function sendRequest($url, $data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $res = curl_exec($ch);
        curl_close ($ch);
        return $res;
    }

    public function sendSMS($phone, $msg){
        $url = 'https://smsc.ru/sys/send.php';
        $data = Array(
            'login' => $this->login,
            'psw' => $this->password,
            'phones' => $phone,
            'mes' => $msg,
            'sender' => 'SMSC.RU',
        );
        return $this->sendRequest($url, $data);
    }

    public function getBalance(){
        $url = 'https://smsc.ru/sys/balance.php';
        $data = Array(
            'login' => $this->login,
            'psw' => $this->password,
        );
        return $this->sendRequest($url, $data);
    }

}
