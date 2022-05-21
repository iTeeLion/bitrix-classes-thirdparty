<?php

namespace Prominado\AmoCRM;


class Api
{

    protected $url = 'amocrm.ru';
    protected $cookie;
    protected $userLogin;
    protected $userHash;
    protected $subdomain;
    protected $amoCreatedByUserId;
    protected $amoResponsibleUserId;
    protected $amoGoodsCatalogID;
    protected $logEvents = false;
    protected $logErrors = true;
    protected $logFile = __DIR__ . '/AmoCRM_api.log';
    protected $errors = array(
        301 => 'Moved permanently',
        400 => 'Bad request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not found',
        500 => 'Internal server error',
        502 => 'Bad gateway',
        503 => 'Service unavailable',
    );

    /**
     * AmoApi constructor.
     */
    public function __construct($params)
    {
        $this->setConfig($params);
        $this->cookie = __DIR__ . '/.cookie.txt';
    }

    /**
     * Конфигурация
     *
     * @param $params
     */
    public function setConfig($params)
    {
        if ($params['userLogin']) $this->userLogin = $params['userLogin'];
        if ($params['userHash']) $this->userHash = $params['userHash'];
        if ($params['subdomain']) $this->subdomain = $params['subdomain'];
        if ($params['amoCreatedByUserId']) $this->amoCreatedByUserId = $params['amoCreatedByUserId'];
        if ($params['amoResponsibleUserId']) $this->amoResponsibleUserId = $params['amoResponsibleUserId'];
        if ($params['amoGoodsCatalogID']) $this->amoGoodsCatalogID = $params['amoGoodsCatalogID'];
        if ($params['logEvents']) $this->logEvents = $params['logEvents'];
        if ($params['logErrors']) $this->logErrors = $params['logErrors'];
        if ($params['logFile']) $this->logFile = $params['logFile'];
    }

    /**
     * Log
     *
     * @param $data
     */
    private function logIt($data)
    {
        $row = '[' . date('Y.m.d H:i:s') . '] ' . $data . PHP_EOL;
        file_put_contents($this->logFile, $row, FILE_APPEND | LOCK_EX);
    }

    private function logEvent($data)
    {
        if ($this->logEvents) {
            $this->logIt($data);
        }
    }

    private function logError($data)
    {
        if ($this->logErrors) {
            $this->logIt('[ERROR]' . $data);
        }
    }

    private function getApiHost()
    {
        return 'https://' . $this->subdomain . '.' . $this->url;
    }

    /**
     * Выполнение curl-запроса по ссылке, опционально с post-полями
     *
     * @param $link
     * @param bool $postFields
     *
     * @return mixed
     * @throws \Exception
     */
    protected function getCurl($link, $postFields = false)
    {
        $curl = curl_init();
        // Устанавливаем необходимые опции для сеанса cURL
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookie);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookie);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        if ($postFields) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        }

        // Выполняем запрос к серверу
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $code = (int)$code;

        // Если код ответа не равен 200 или 204 - возвращаем сообщение об ошибке
        if ($code !== 200 && $code !== 204) {
            $this->logError("getCurl: exception 1" . PHP_EOL . print_r($link, true) . PHP_EOL . print_r($postFields, true));
            throw new \Exception(isset($this->errors[$code]) ? $this->errors[$code] : 'Undescribed error', $code);
        }

        return $out;
    }

    /**
     * Авторизует, сохраняя куку
     *
     * @return bool
     * @throws \Exception
     */
    public function auth()
    {
        #Массив с параметрами, которые нужно передать методом POST к API системы
        $user = array(
            'USER_LOGIN' => $this->userLogin,   #Ваш логин (электронная почта)
            'USER_HASH' => $this->userHash     #Хэш для доступа к API (смотрите в профиле пользователя)
        );

        $link = $this->getApiHost() . '/private/api/auth.php?type=json';

        $response = json_decode($this->getCurl($link, json_encode($user)), true);
        $response = $response['response'];

        if (isset($response['auth'])) #Флаг авторизации доступен в свойстве "auth"
        {
            return true;
        }

        return false;
    }

    /**
     * Создает новый контакт по параметрам, возвращает id
     *
     * @param $params
     *
     * @return mixed
     * @throws \Exception
     */
    public function createContact($params)
    {
        $actionTitle = 'createContact: ';
        $this->logEvent($actionTitle . PHP_EOL . print_r($params, true));

        if (!$params->name) {
            $this->logError($actionTitle . 'Missing params' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Missing params');
        }

        $customFields = array();
        foreach ($params->customFields as $id => $values) {
            $customFields[] = array(
                'id' => $id,
                'values' => $values,
            );
        }

        $contacts['add'] = array(
            array(
                'name' => $params->name,
                'responsible_user_id' => $this->amoResponsibleUserId,
                'created_by' => $this->amoCreatedByUserId,
                'created_at' => time(),
                'updated_at' => time(),
                'custom_fields' => $customFields,
            ),
        );

        if ($params->tags) {
            $contacts['add'][0]['tags'] = implode(',', $params->tags);
        }

        $link = $this->getApiHost() . '/api/v2/contacts';
        $response = json_decode($this->getCurl($link, json_encode($contacts)))->_embedded->items;
        $responseId = $response[0]->id;

        if (!$responseId) {
            $this->logError($actionTitle . 'Creation failed' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Creation failed');
        }

        return $responseId;
    }

    /**
     * Обновляет контакт по параметрам, требует id, возвращает id
     *
     * @param $params
     *
     * @return mixed
     * @throws \Exception
     */
    public function updateContact($params)
    {
        $actionTitle = 'updateContact: ';
        $this->logEvent($actionTitle . PHP_EOL . print_r($params, true));

        if (!$params->id) {
            $this->logError($actionTitle . 'Missing params' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Missing params');
        }

        $customFields = array();
        foreach ($params->customFields as $id => $values) {
            $customFields[] = array(
                'id' => $id,
                'values' => $values,
            );
        }

        $contacts['update'] = array(
            array(
                'id' => $params->id,
                'updated_at' => time(),
            ),
        );

        if ($params->name) {
            $contacts['update'][0]['name'] = $params->name;
        }

        if ($params->tags) {
            $contacts['update'][0]['tags'] = implode(',', $params->tags);
        }

        if ($customFields) {
            $contacts['update'][0]['custom_fields'] = $customFields;
        }

        $link = $this->getApiHost(). '/api/v2/contacts';
        $response = json_decode($this->getCurl($link, json_encode($contacts)))->_embedded->items;
        $responseId = $response[0]->id;

        if (!$responseId) {
            $this->logError($actionTitle . 'Updating failed' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Updating failed');
        }

        return $responseId;
    }

    /**
     * Создает товар в каталоге
     *
     * @param $params
     * @return mixed
     * @throws \Exception
     */
    public function createGood($params)
    {
        $actionTitle = 'createGood: ';
        $this->logEvent($actionTitle . PHP_EOL . print_r($params, true));

        //if (!$params->name || !$params->catalog_id) {
        if (!$params->name) {
            $this->logError($actionTitle . 'Missing params' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Missing params');
        }

        $customFields = array();
        foreach ($params->customFields as $id => $values) {
            $customFields[] = array(
                'id' => $id,
                'values' => $values,
            );
        }

        $goods['add'] = array(
            array(
                'name' => $params->name,
                'request_id' => $params->request_id,
                'catalog_id' => $this->amoGoodsCatalogID, //$params->catalog_id,
                'custom_fields' => $customFields,
            ),
        );

        if ($params->tags) {
            $goods['add'][0]['tags'] = implode(',', $params->tags);
        }

        $link = $this->getApiHost() . '/api/v2/catalog_elements';
        $response = json_decode($this->getCurl($link, json_encode($goods)))->_embedded->items;
        $responseId = $response[0]->id;

        if (!$responseId) {
            $this->logError($actionTitle . 'Creation failed' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Creation failed');
        }

        return $responseId;
    }

    /**
     * Обновляет товар
     *
     * @param $params
     *
     * @return mixed
     * @throws \Exception
     */
    public function updateGood($params)
    {
        $actionTitle = 'updateGood: ';
        $this->logEvent($actionTitle . PHP_EOL . print_r($params, true));

        if (!$params->id) {
            $this->logError($actionTitle . 'Missing params' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Missing params');
        }

        $customFields = array();
        foreach ($params->customFields as $id => $values) {
            $customFields[] = array(
                'id' => $id,
                'values' => $values,
            );
        }

        $goods['update'] = array(
            array(
                'id' => $params->id,
                'catalog_id' => $this->amoGoodsCatalogID, //$params->catalog_id,
            ),
        );

        if ($params->name) {
            $goods['update'][0]['name'] = $params->name;
        }

        if (count($customFields) > 0) {
            $goods['update'][0]['custom_fields'] = $customFields;
        }

        $link = $this->getApiHost() . '/api/v2/catalog_elements';
        $response = json_decode($this->getCurl($link, json_encode($goods)))->_embedded->items;
        $responseId = $response[0]->id;

        if (!$responseId) {
            $this->logError($actionTitle . 'Updating failed' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Updating failed');
        }

        return $responseId;
    }

    /**
     * Создает сделку
     *
     * @param $params
     * @return mixed
     * @throws \Exception
     */
    public function createDeal($params)
    {
        $actionTitle = 'createDeal: ';
        $this->logEvent($actionTitle . PHP_EOL . print_r($params, true));

        if (!$params->name) {
            $this->logError($actionTitle . 'Missing params' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Missing params');
        }

        $customFields = array();
        foreach ($params->customFields as $id => $values) {
            $customFields[] = array(
                'id' => $id,
                'values' => $values,
            );
        }

        $goods['add'] = array(
            array(
                'name' => $params->name,
                'responsible_user_id' => $this->amoResponsibleUserId,
                'created_by' => $this->amoCreatedByUserId,
                'created_at' => time(),
                'updated_at' => time(),
                //'status_id' => 1,
                'status_id' => $params->status_id,
                'sale' => $params->sale,
                'contacts_id' => $params->contacts_id,
                'custom_fields' => $customFields,
            ),
        );

        if ($params->tags) {
            $goods['add'][0]['tags'] = implode(',', $params->tags);
        }

        $link = $this->getApiHost() . '/api/v2/leads';
        $response = json_decode($this->getCurl($link, json_encode($goods)))->_embedded->items;
        $responseId = $response[0]->id;

        if (!$responseId) {
            $this->logError($actionTitle . 'Creation failed' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Creation failed');
        }

        return $responseId;
    }


    public function updateDeal($params)
    {
        $actionTitle = 'updateDeal';
        $this->logEvent($actionTitle . PHP_EOL . print_r($params, true));

        if (!$params->id) {
            $this->logError($actionTitle . 'Missing params' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Missing params');
        }

        $customFields = array();
        foreach ($params->customFields as $id => $values) {
            $customFields[] = array(
                'id' => $id,
                'values' => $values,
            );
        }

        $contacts['update'] = array(
            array(
                'id' => $params->id,
                'responsible_user_id' => $this->amoResponsibleUserId,
                'updated_at' => time(),
            ),
        );

        if ($params->name) {
            $contacts['update'][0]['name'] = $params->name;
        }

        if ($params->status_id) {
            $contacts['update'][0]['status_id'] = $params->status_id;
        }

        if ($params->sale) {
            $contacts['update'][0]['sale'] = $params->sale;
        }

        if ($params->contacts_id) {
            $contacts['update'][0]['contacts_id'] = $params->contacts_id;
        }

        if ($params->tags) {
            $contacts['update'][0]['tags'] = implode(',', $params->tags);
        }

        if ($customFields) {
            $contacts['update'][0]['custom_fields'] = $customFields;
        }

        $link = $this->getApiHost() . '/api/v2/leads';
        $response = json_decode($this->getCurl($link, json_encode($contacts)))->_embedded->items;
        $responseId = $response[0]->id;

        if (!$responseId) {
            $this->logError($actionTitle . 'Updating failed' . PHP_EOL . print_r($params, true));
            throw new \Exception($actionTitle . 'Updating failed');
        }

        return $responseId;
    }

}