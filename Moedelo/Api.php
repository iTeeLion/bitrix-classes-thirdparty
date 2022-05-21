<?php

namespace Prominado\Moedelo;


class Api
{

    protected $key;
    protected $pageSize = 100;
    protected $logEvents = false;
    protected $logErrors = true;
    protected $logFilesPath = __DIR__ . '/logs/';

    //protected $settlementAccountId;

    public function __construct($params)
    {
        $this->setConfig($params);
    }

    public function setConfig($params)
    {
        if (isset($params['key'])) $this->key = $params['key'];
        if (isset($params['pageSize'])) $this->pageSize = $params['pageSize'];
        if (isset($params['logEvents'])) $this->logEvents = $params['logEvents'];
        if (isset($params['logErrors'])) $this->logErrors = $params['logErrors'];
        if (isset($params['logFilesPath'])) $this->logFilesPath = $params['logFilesPath'];
        //if(isset($params['settlementAccountId'])) $this->settlementAccountId = $params['settlementAccountId'];
    }

    public function getKey()
    {
        return $this->key;
    }

    /**
     * Make log row with time
     *
     * @param $msg
     * @return string
     */
    protected function logIt($data, $fileName = 'md')
    {
        ob_start();
        echo date('[Y-m-d H:i:s] ');
        var_dump($data);
        echo PHP_EOL;
        $ob = ob_get_clean();
        file_put_contents($this->logFilesPath . '/' . $fileName . '.log', $ob, FILE_APPEND);
    }

    protected function logEvent($data, $eventName = 'md-event')
    {
        if ($this->logEvents) {
            $this->logIt($data, 'md_' . $eventName . '_events');
        }
    }

    protected function logError($data, $errorName = 'md-errors')
    {
        if ($this->logErrors) {
            $this->logIt($data, 'md_' . $errorName . '_errors');
        }
    }

    /**
     * Отправка запроса к "Мое дело" API
     *
     * @param $method
     * @param $url
     * @param $params
     * @return array|mixed
     */
    private function sendRequest($method, $url, $params)
    {
        $res = Array();
        if ($this->key) {

            $server = 'https://restapi.moedelo.org';
            if ($method == 'GET') {
                $paramsGET = '?' . http_build_query($params);
            } else {
                $paramsPOST = http_build_query($params);
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_URL, $server . $url . $paramsGET);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, Array(
                'Accept: application/json',
                'md-api-key: ' . $this->key,
            ));
            if ($paramsPOST) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $paramsPOST);
            }
            $data = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            $res = $info;
            $res['response'] = json_decode($data, true);

            if (count($res['response']['ValidationErrors'])) {
                $this->logError($res['response']['ValidationErrors'], 'api-query-error');
            }

        } else {

            $res['errors'][] = 'Api key not set';

        }
        return $res;
    }

    /**
     * Получить список складов
     *
     * @param $params Array(pageNo, pageSize)
     * @return array|mixed
     */
    public function getStocksList($params)
    {
        $url = '/stock/api/v1/stock';
        $res = $this->sendRequest('GET', $url, $params);
        return $res;
    }

    /**
     * Получить список категорий товаров
     *
     * @param $params Array(pageNo, pageSize)
     * @return array|mixed
     */
    public function getGoodsGroupsList($params)
    {
        $url = '/stock/api/v1/nomenclature';
        $res = $this->sendRequest('GET', $url, $params);
        return $res;
    }

    /**
     * Получить список товаров
     *
     * @param $params Array(pageNo, pageSize, afterDate, beforeDate, name)
     * @return array|mixed
     */
    public function getGoodsList($params)
    {
        $url = '/stock/api/v1/good';
        $res = $this->sendRequest('GET', $url, $params);
        return $res;
    }

    /**
     * Получить количество товаров по их ID
     *
     * @param $params Array(id, id, ...)
     * @return array|mixed
     */
    public function getGoodsCountInStocks($params)
    {
        $url = '/stock/api/v1/good/remains';
        $res = $this->sendRequest('POST', $url, $params);
        return $res;
    }

    /**
     * Создать контрагента
     *
     * @param $params Array('Inn', 'Ogrn', 'Okpo', 'Name', 'Type', 'LegalAddress', 'ActualAddress')
     * @return array|mixed
     */
    public function addKontragent($params)
    {
        $url = '/kontragents/api/v1/kontragent';
        unset($params['Id']);
        $res = $this->sendRequest('POST', $url, $params);
        return $res;
    }

    /**
     * Обновить контрагента
     *
     * @param $params Array('Id', 'Inn', 'Ogrn', 'Okpo', 'Name', 'Type', 'LegalAddress', 'ActualAddress')
     * @return array|mixed
     */
    public function updateKontragent($params)
    {
        if ($params['Id']) {
            $url = '/kontragents/api/v1/kontragent/' . $params['Id'];
            unset($params['Id']);
            $res = $this->sendRequest('PUT', $url, $params);
        } else {
            $res['errors'][] = 'Kontragent id not set';
        }
        return $res;
    }

    /**
     * Создать/Обновить контрагента (wrapper)
     *
     * @param $params Array('Id', 'Inn', 'Ogrn', 'Okpo', 'Name', 'Type', 'LegalAddress', 'ActualAddress')
     * @return array|mixed
     */
    public function setKontragent($params)
    {
        if ($params['Id']) {
            $res = $this->updateKontragent($params);
        } else {
            $res = $this->addKontragent($params);
        }
        return $res;
    }

    /**
     * Создать контакт контрагента
     *
     * @param $params Array('Fio', 'Skype', 'Emails', 'Phones')
     * @return array|mixed
     */
    public function addKontragentContact($params)
    {
        if ($params['KontragentId']) {
            $url = '/kontragents/api/v1/kontragent/' . $params['KontragentId'] . '/contact';
            unset($params['Id']);
            unset($params['KontragentId']);
            $res = $this->sendRequest('POST', $url, $params);
        } else {
            $res['errors'][] = 'Kontragent id not set for contacts';
        }
        return $res;
    }

    /**
     * Обновить контакт контрагента
     *
     * @param $params Array('Id', Fio', 'Skype', 'Emails', 'Phones')
     * @return array|mixed
     */
    public function updateKontragentContact($params)
    {
        if ($params['KontragentId'] || $params['Id']) {
            $url = '/kontragents/api/v1/kontragent/' . $params['KontragentId'] . '/contact/' . $params['Id'];
            unset($params['Id']);
            unset($params['KontragentId']);
            $res = $this->sendRequest('PUT', $url, $params);
        } else {
            $res['errors'][] = 'Kontragent id & Contact id not set';
        }
        return $res;
    }

    /**
     * Создать/Обновить контрагента (wrapper0
     *
     * @param $params Array('Id', Fio', 'Skype', 'Emails', 'Phones')
     * @return array|mixed
     */
    public function setKontragentContact($params)
    {
        if ($params['Id']) {
            $res = $this->updateKontragentContact($params);
        } else {
            $res = $this->addKontragentContact($params);
        }
        return $res;
    }

    /**
     * Создать счет
     *
     * @param $params
     * @return array|mixed
     */
    public function addBill($params)
    {
        $url = '/accounting/api/v1/sales/bill';
        unset($params['Id']);
        $res = $this->sendRequest('POST', $url, $params);
        return $res;
    }

    /**
     * Обновить счет
     *
     * @param $params
     * @return array|mixed
     */
    public function updateBill($params)
    {
        $url = '/accounting/api/v1/sales/bill/' . $params['Id'];
        $res = $this->sendRequest('PUT', $url, $params);
        return $res;
    }

    /**
     * Создать/Обновить счет
     *
     * @param $params
     * @return array|mixed
     */
    public function setBill($params)
    {
        if ($params['Id']) {
            $res = $this->updateBill($params);
        } else {
            $res = $this->addBill($params);
        }
        return $res;
    }

    /**
     * Удалить счет
     *
     * @param $params
     * @return array|mixed
     */
    public function removeBill($params)
    {
        $url = '/accounting/api/v1/sales/bill/' . $params['Id'];
        unset($params['Id']);
        $res = $this->sendRequest('DELETE', $url, $params);
        return $res;
    }

}