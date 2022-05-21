<?php

namespace Prominado\Moedelo;


class Bitrix
{

    protected $api;
    protected $modifiedByUserId = 1;
    protected $ibCatalogId = 27;
    protected $settlementAccountId;
    protected $stockMdIdUfCode = 'UF_MD_ID';
    protected $goodCategoryMdIdUfCode = 'UF_MD_ID';
    protected $goodMdIdPropCode = 'PROPERTY_MD_ID';
    protected $goodArticlePropCode = 'PROPERTY_ARTICLE';
    protected $companyRequisiteMdIdUfCode;
    protected $companyContactMdIdUfCode;
    protected $invoiceMdIdUfCode;
    protected $logEvents = false;
    protected $logErrors = true;
    protected $logFilesPath = __DIR__ . '/logs/';

    public function __construct($params)
    {
        if (isset($params['logEvents'])) $params['api']['logEvents'] = $params['logEvents'];
        if (isset($params['logErrors'])) $params['api']['logErrors'] = $params['logErrors'];
        if (isset($params['logFilesPath'])) $params['api']['logFilesPath'] = $params['logFilesPath'];
        $this->api = new Api($params['api']);
        $this->setConfig($params);
    }

    public function setConfig($params)
    {
        if (isset($params['modifiedByUserId'])) $this->modifiedByUserId = $params['modifiedByUserId'];
        if (isset($params['ibCatalogId'])) $this->ibCatalogId = $params['ibCatalogId'];
        if (isset($params['settlementAccountId'])) $this->settlementAccountId = $params['settlementAccountId'];
        if (isset($params['stockMdIdUfCode'])) $this->stockMdIdUfCode = $params['stockMdIdUfCode'];
        if (isset($params['goodCategoryMdIdUfCode'])) $this->goodCategoryMdIdUfCode = $params['goodCategoryMdIdUfCode'];
        if (isset($params['goodMdIdPropCode'])) $this->goodMdIdPropCode = $params['goodMdIdPropCode'];
        if (isset($params['companyRequisiteMdIdUfCode'])) $this->companyRequisiteMdIdUfCode = $params['companyRequisiteMdIdUfCode'];
        if (isset($params['companyContactMdIdUfCode'])) $this->companyContactMdIdUfCode = $params['companyContactMdIdUfCode'];
        if (isset($params['invoiceMdIdUfCode'])) $this->invoiceMdIdUfCode = $params['invoiceMdIdUfCode'];
        if (isset($params['logEvents'])) $this->logEvents = $params['logEvents'];
        if (isset($params['logErrors'])) $this->logErrors = $params['logErrors'];
        if (isset($params['logFilesPath'])) $this->logFilesPath = $params['logFilesPath'];
    }

    public function getKey()
    {
        return $this->api->getKey();
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

    // To remove
    public function log($msg)
    {
        return '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    }

    /**
     * Print res array
     *
     * @param $res
     */
    public function showResults($res)
    {
        echo '<pre>';
        if (count($res['errors'])) {
            print_r($res['errors']);
        } else {
            print_r($res['log']);
        }
        echo '</pre>';
    }

    /**
     * Format datetime to MD format
     *
     * @param $datetime
     * @return false|string
     */
    public function formatDateTime($datetime)
    {
        $datetime = str_replace("'", '', $datetime);
        return date('c', strtotime($datetime));
    }

    /**
     * Get entity requisites base method
     *
     * @param $arFilter
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    private function bxGetRequisites($arFilter)
    {
        \Bitrix\Main\Loader::includeModule('crm');
        $EntityRequisite = new \Bitrix\Crm\EntityRequisite;
        $dbRes = $EntityRequisite->getList([
            'filter' => $arFilter
        ]);
        $arRequisites = Array();
        while ($arRequisite = $dbRes->fetch()) {
            $arRequisiteUF = $GLOBALS["USER_FIELD_MANAGER"]->GetUserFields($EntityRequisite->getUfId(), $arRequisite["ID"]);
            $arRequisite = array_merge($arRequisite, $arRequisiteUF);
            $arRequisites[] = $arRequisite;
        }
        return $arRequisites;
    }

    /**
     * Get entity requisites by entity ID and Type
     *
     * @param $entityID
     * @param $entityTypeID
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    private function bxGetRequisitesByEID($entityID, $entityTypeID)
    {
        $arFilter = ['ENTITY_ID' => $entityID, 'ENTITY_TYPE_ID' => $entityTypeID];
        $arRequisites = $this->bxGetRequisites($arFilter);
        return $arRequisites;
    }

    /**
     * Get entity requisites by ID and Type
     *
     * @param $ID
     * @param $entityTypeID
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    private function bxGetRequisitesByID($ID, $entityTypeID)
    {
        $arFilter = ['ID' => $ID, 'ENTITY_TYPE_ID' => $entityTypeID];
        $arRequisites = $this->bxGetRequisites($arFilter);
        return $arRequisites;
    }

    /**
     * Get linked entity requisites id by entity id and entity type
     *
     * @param $entityID
     * @param $entityTypeID
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    private function bxGetLinkedRequisitesID($entityID, $entityTypeID)
    {
        \Bitrix\Main\Loader::includeModule('crm');
        $EntityRequisite = new \Bitrix\Crm\EntityRequisite();
        $rqEntity[] = array('ENTITY_ID' => $entityID, 'ENTITY_TYPE_ID' => $entityTypeID);
        $rqLinked = $EntityRequisite->getDefaultRequisiteInfoLinked($rqEntity);
        return $rqLinked;
    }

    /**
     * Get linked requisites
     *
     * @param $entityID
     * @param $entityTypeID
     * @param $relatedEntityTypeID
     * @return mixed
     */
    private function bxGetLinkedRequisitesByID($entityID, $entityTypeID, $relatedEntityTypeID)
    {
        $linkedRQ = $this->bxGetLinkedRequisitesID($entityID, $entityTypeID);
        $arRQ = $this->bxGetRequisitesByID($linkedRQ['REQUISITE_ID'], $relatedEntityTypeID);
        return $arRQ;
    }

    /**
     * Get entity addresses by entity ID and Type
     *
     * @param $entityID
     * @param $entityTypeID
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    private function bxGetAddressesByID($entityID, $entityTypeID)
    {
        \Bitrix\Main\Loader::includeModule('crm');
        $EntityAddress = new \Bitrix\Crm\EntityAddress;
        $dbRes = $EntityAddress->getList([
            'filter' => ['ENTITY_ID' => $entityID, 'ENTITY_TYPE_ID' => $entityTypeID]
        ]);
        $arAddresses = $dbRes->fetchAll();
        return $arAddresses;
    }

    /**
     * Get company contact info
     *
     * @param $companyID
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    private function bxGetCompanyContact($companyID)
    {
        \Bitrix\Main\Loader::includeModule('crm');
        $CCrmFieldMulti = new \CCrmFieldMulti;
        $arFilter = Array('ENTITY_ID' => 'COMPANY', 'ELEMENT_ID' => $companyID);
        $dbRes = $CCrmFieldMulti::GetList(arOrder, $arFilter);
        $arCompanyContact = Array();
        while ($item = $dbRes->Fetch()) {
            if ($item['COMPLEX_ID'] == 'WEB_WORK') {
                $arCompanyContact['Site'] = $item['VALUE'];
            }
            if ($item['COMPLEX_ID'] == 'IM_SKYPE') {
                if (strlen($item['VALUE']) >= 6 && strlen($item['VALUE']) <= 20 && preg_match("/^[a-zA-Z0-9.]+$/i", $item['VALUE'])) {
                    $arCompanyContact['Skype'] = $item['VALUE'];
                }
            }
            if ($item['TYPE_ID'] == 'PHONE') {
                $arCompanyContact['Phones'][] = Array('Number' => $item['VALUE'], 'Description' => $item['VALUE_TYPE']);
            }
            if ($item['TYPE_ID'] == 'EMAIL') {
                $arCompanyContact['Emails'][] = Array('Email' => $item['VALUE'], 'Description' => $item['VALUE_TYPE']);
            }
        }
        return $arCompanyContact;
    }

    /**
     * Get invoice MD ID
     *
     * @param $IID
     * @return mixed
     * @throws \Bitrix\Main\LoaderException
     */
    private function bxGetInvoiceMDID($IID)
    {
        \Bitrix\Main\Loader::includeModule('crm');
        $arFilter = Array('ID' => $IID);
        $arSelect = Array($this->invoiceMdIdUfCode);
        $dbRes = \CCrmInvoice::GetList(Array(), $arFilter, false, false, $arSelect, Array());
        while ($item = $dbRes->GetNext()) {
            $res = $item[$this->invoiceMdIdUfCode];
        }
        return $res;
    }

    /**
     * Get goods MD IDs
     *
     * @param $GIDs
     * @return mixed
     */
    private function bxGetGoodsMDID($goodsIDs)
    {
        $arFilter = Array('IBLOCK_ID' => $this->ibCatalogId, 'ID' => $goodsIDs);
        $arSelect = Array('ID', $this->goodMdIdPropCode);
        $dbRes = \CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
        $res = Array();
        while ($item = $dbRes->GetNext()) {
            $res[$item['ID']] = $item;
        }
        return $res;
    }

    public function bxSetKontragentByReqID($ReqID)
    {
        $EntityRequisite   = new \Bitrix\Crm\EntityRequisite();
        $dbRequisites = $EntityRequisite->getList(array('filter' => array('ID' => $ReqID), 'select' => array('ENTITY_ID')));
        while($arRequisite = $dbRequisites->Fetch()){
            $CID = $arRequisite['ENTITY_ID'];
        }
        if($CID){
            $arCompany = \CCrmCompany::GetByID($CID, false);
        }
        return $this->bxSetKontragent($arCompany);
    }

    public function bxSetKontragentByReq_EventHandler($ReqID)
    {
        $res = $this->bxSetKontragentByReqID($ReqID);
        return true;
    }

    /**
     * Sync kontragent with MD
     *
     * @param $arFields (provides by OnAfterCrmCompanyAdd/Update event)
     * @return mixed
     * @throws \Bitrix\Main\LoaderException
     */
    public function bxSetKontragent($arFields)
    {
        if ($this->companyRequisiteMdIdUfCode && $this->companyContactMdIdUfCode && $arFields['ID']) {
            switch ($arFields['COMPANY_TYPE']) {
                case 'CUSTOMER':
                    $type = 2;
                    break;
                case 'SUPPLIER':
                    $type = 3;
                    break;
                default:
                    $type = 4;
                    break;
            }
            $arCompanyContact = $this->bxGetCompanyContact($arFields['ID']);
            $arRequisitesList = $this->bxGetRequisitesByEID($arFields['ID'], \CCrmOwnerType::Company);
            foreach ($arRequisitesList as $arRQ) {
                $arAddrs = Array();
                $arAddressesList = $this->bxGetAddressesByID($arRQ['ID'], \CCrmOwnerType::Requisite);
                foreach ($arAddressesList as $addr) {
                    $addrStr = implode(', ', Array(
                        $addr['COUNTRY'],
                        $addr['POSTAL_CODE'],
                        $addr['REGION'],
                        $addr['PROVINCE'],
                        $addr['CITY'],
                        $addr['ADDRESS_1'],
                        $addr['ADDRESS_2'],
                    ));
                    switch ($addr['TYPE_ID']) {
                        case 1:
                            $arAddrs['ActualAddress'] = $addrStr;
                            break;
                        case 4:
                            $arAddrs['RegistrationAddress'] = $addrStr;
                            break;
                        case 6:
                            $arAddrs['LegalAddress'] = $addrStr;
                            break;
                    }
                }
                $qParams = Array(
                    'Name' => $arFields['TITLE'] . ' (' . $arRQ['NAME'] . ')',
                    'Type' => $type,
                    'SiteUrl' => $arCompanyContact['Site'],
                    'Inn' => $arRQ['RQ_INN'],
                );
                if ($arRQ['PRESET_ID'] == 3) {
                    $qParams['Fio'] = implode(' ', Array($arRQ['RQ_FIRST_NAME'], $arRQ['RQ_LAST_NAME'], $arRQ['RQ_SECOND_NAME']));
                    $qParams['RegistrationAddress'] = $arAddrs['RegistrationAddress'];
                } else {
                    $qParams['ShortName'] = $arRQ['RQ_COMPANY_NAME'];
                    $qParams['FullName'] = $arRQ['RQ_COMPANY_FULL_NAME'];
                    $qParams['Okpo'] = $arRQ['RQ_OKPO'];
                    if ($arRQ['PRESET_ID'] == 1) {
                        $qParams['Kpp'] = $arRQ['RQ_KPP'];
                        $qParams['Ogrn'] = $arRQ['RQ_OGRN'];
                    } else {
                        $qParams['Ogrn'] = $arRQ['RQ_OGRNIP'];
                    }
                    $qParams['LegalAddress'] = $arAddrs['LegalAddress'];
                    $qParams['ActualAddress'] = $arAddrs['ActualAddress'];
                }
                if ($arRQ[$this->companyRequisiteMdIdUfCode]['VALUE']) {
                    $qParams['Id'] = $arRQ[$this->companyRequisiteMdIdUfCode]['VALUE'];
                }
                // Set kontragent
                $this->logEvent( $qParams, 'set-kontragent');
                $mdqRes = $this->api->setKontragent($qParams);
                $this->logEvent( $mdqRes, 'set-kontragent');
                $kontragentID = $mdqRes['response']['Id'];
                if (!$qParams['Id']) {
                    $GLOBALS["USER_FIELD_MANAGER"]->Update('CRM_REQUISITE', $arRQ[$this->companyRequisiteMdIdUfCode]['ENTITY_VALUE_ID'], Array($this->companyRequisiteMdIdUfCode => $kontragentID));
                }
                $res['response'] = $mdqRes;
                if ($mdqRes['response']['Id']) {
                    $qParamsContact = $arCompanyContact;
                    $qParamsContact['KontragentId'] = $kontragentID;
                    $qParamsContact['Id'] = $arRQ[$this->companyContactMdIdUfCode]['VALUE'];
                    $qParamsContact['Fio'] = 'Контрагент';
                    unset($qParamsContact['Site']);
                    $this->logEvent( $qParamsContact, 'set-contact');
                    $mdqRes = $this->api->setKontragentContact($qParamsContact);
                    $this->logEvent( $mdqRes, 'set-contact');
                    $contactID = $mdqRes['response']['Id'];
                    if ($contactID) {
                        if (!$qParamsContact['Id']) {
                            $GLOBALS["USER_FIELD_MANAGER"]->Update('CRM_REQUISITE', $arRQ[$this->companyContactMdIdUfCode]['ENTITY_VALUE_ID'], Array($this->companyContactMdIdUfCode => $contactID));
                        }
                    } else {
                        foreach ($mdqRes['response']['ValidationErrors'] as $error) {
                            $res['errors'][] = $error;
                        }
                    }
                    $res['response'] = $mdqRes;
                } else {
                    foreach ($mdqRes['response']['ValidationErrors'] as $error) {
                        $res['errors'][] = $error;
                    }
                }
            }
            return $res;
        } else {
            $res['errors'][] = Array('MDID Company/Company contact not set');
            return $res;
        }
    }

    /**
     * Event handler for bxSetKontragent()
     *
     * @param $arFields
     * @throws \Bitrix\Main\LoaderException
     */
    public function bxSetKontragent_EventHandler($arFields)
    {
        $res = $this->bxSetKontragent($arFields);
        return true;
    }

    /**
     * Sync invoice with MD (by event)
     *
     * @param $arFields (provides by OnBeforeCrmInvoiceAdd/Update event)
     * @return mixed
     */
    public function bxSetInvoice($arFields)
    {
        \Bitrix\Main\Loader::includeModule('crm');
        $CCrmInvoice = new \CCrmInvoice(false);
        $invMDID = $this->bxGetInvoiceMDID($arFields['ID']);
        $arRQ = $this->bxGetLinkedRequisitesByID($arFields['ID'], \CCrmOwnerType::Invoice, \CCrmOwnerType::Company);
        $arRQ = array_shift($arRQ);

        if ($this->settlementAccountId) {
            $items = $arFields['PRODUCT_ROWS'];
            $itemIDs = Array();
            $arGoods = Array();
            foreach ($items as $item) {
                $itemIDs[] = $item['PRODUCT_ID'];
                if ($item['VAT_INCLUDED'] = 'Y') {
                    $VAT_INCLUDED = 3;
                } else {
                    $VAT_INCLUDED = 2;
                }

            }
            switch ($arFields['STATUS_ID']) {
                case 'P':
                    $STATUS_ID = 6;
                    break;
                default:
                    $STATUS_ID = 4;
                    break;
            }

            $arGoodsMDIDs = $this->bxGetGoodsMDID($itemIDs);
            foreach ($items as $item) {
                $arGoods[] = Array(
                    'StockProductId' => $arGoodsMDIDs[$item['PRODUCT_ID']][$this->goodMdIdPropCode . '_VALUE'],
                    'Name' => $item['PRODUCT_NAME'],
                    'Count' => $item['QUANTITY'],
                    'Unit' => $item['MEASURE_NAME'],
                    'Price' => $item['PRICE'],
                    'NdsType' => (int)($item['VAT_RATE'] * 100),
                    'Type' => 1,
                );
            }
            $arInvoice = Array(
                'Number' => $arFields['ID'],
                'SettlementAccountId' => $this->settlementAccountId,
                'KontragentId' => $arRQ[$this->companyRequisiteMdIdUfCode]['VALUE'],
                'AdditionalInfo' => $arFields['USER_DESCRIPTION'],
                'ContractSubject' => $arFields['ORDER_TOPIC'],
                'NdsPositionType' => $VAT_INCLUDED,
                'Type' => 1,
                'Status' => $STATUS_ID,
                //'ProjectId' => 283643,
                //'IsCovered' => true,
                'Items' => $arGoods,
            );
            if ($arFields['~DATE_BILL']) {
                $arInvoice['DocDate'] = $this->formatDateTime($arFields['~DATE_BILL']);
            }
            if ($arFields['~DATE_PAY_BEFORE']) {
                $arInvoice['DeadLine'] = $this->formatDateTime($arFields['~DATE_PAY_BEFORE']);
            }
            if ($invMDID) {
                $arInvoice['Id'] = $invMDID;
                $this->logEvent( $arInvoice, 'upd-invoice');
                $qResult = $this->api->setBill($arInvoice);
                $this->logEvent($qResult, 'upd-invoice');
            } else {
                $this->logEvent($arInvoice, 'add-invoice');
                $qResult = $this->api->setBill($arInvoice);
                $this->logEvent($qResult, 'add-invoice');
                if ($qResult['response']['Id']) {
                    $MDID = $qResult['response']['Id'];
                    $newInvoice[$this->invoiceMdIdUfCode] = $MDID;
                    $result = $CCrmInvoice->Update($arFields['ID'], $newInvoice);
                }
            }
            return true;
        } else {
            $res['errors'][] = Array('SettlementAccountId not set!');
            return $res;
        }
    }

    /**
     * Event handler for bxSetInvoice()
     *
     * @param $arFields
     */
    public function bxSetInvoice_EventHandler($arFields)
    {
        $res = $this->bxSetInvoice($arFields);
        if (count($res['errors']) > 0) {
            ShowError("Ошибка!");
        }
    }

    /**
     * Sync stocks (for cron)
     *
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     * @throws \Exception
     */
    function bxSyncStocksList()
    {
        \Bitrix\Main\Loader::includeModule('catalog');
        $CatalogStore = new \Bitrix\Catalog\StoreTable;
        $result = Array();
        $page = 1;
        while ($page) {
            $mdRes = $this->api->getStocksList(Array('pageNo' => $page, 'pageSize' => $this->pageSize));

            if ($mdRes['response']) {

                $page++;

                // Prepare ids
                $mdItemsIDs = $toUpdate = Array();
                foreach ($mdRes['response']['ResourceList'] as $item) {
                    $mdItemsIDs[$item['Id']] = $item['Id'];
                }
                $dbRes = $CatalogStore::getList(Array(
                    'select' => Array('ID', 'TITLE', $this->stockMdIdUfCode),
                ));
                while ($item = $dbRes->fetch()) {
                    if (in_array($item[$this->stockMdIdUfCode], $mdItemsIDs)) {
                        $toUpdate[$item[$this->stockMdIdUfCode]] = $item['ID'];
                    } else {
                        $toDisable[$item['ID']] = $item['ID'];
                    }
                }

                // Add and update
                foreach ($mdRes['response']['ResourceList'] as $mdItem) {
                    $arItem = Array(
                        'SITE_ID' => 1,
                        'ACTIVE' => 'Y',
                        'MODIFIED_BY' => $this->modifiedByUserId,
                        'TITLE' => $mdItem['Name'],
                    );
                    if ($toUpdate[$mdItem['Id']]) {
                        $op = 'Update';
                        $dbRes = $CatalogStore::Update($toUpdate[$mdItem['Id']], $arItem);
                    } else {
                        $arItem['CREATED_BY'] = $this->modifiedByUserId;
                        $arItem['ADDRESS'] = 'ADDRESS';
                        $arItem[$this->stockMdIdUfCode] = $mdItem['Id'];
                        $op = 'Add';
                        $dbRes = $CatalogStore::add($arItem);
                    }
                    if ($dbRes->isSuccess()) {
                        $msg = $op . ': ' . $mdItem['Id'] . ' -> ' . $dbRes->getId();
                    } else {
                        $msg = 'Error: ' . $dbRes->getErrorMessages();
                    }
                    $result['log'][] = $this->log($msg);
                }

                // Disable
                foreach ($toDisable as $disableID) {
                    $arItem = Array(
                        'ACTIVE' => 'N',
                        'MODIFIED_BY' => $this->modifiedByUserId,
                    );
                    $op = 'Remove';
                    $dbRes = $CatalogStore::Update($disableID, $arItem);
                    if ($dbRes->isSuccess()) {
                        $msg = $op . ': ' . $dbRes->getId();
                    } else {
                        $msg = 'Error: ' . $dbRes->getErrorMessages();
                    }
                    $result['log'][] = $this->log($msg);
                }

            } else {

                $page = 0;
                if (count($mdRes['errors']) > 0) {
                    $result['errors'] = $mdRes['errors'];
                }

            }
        }
        return $result;
    }

    /**
     * Sync goods groups (for cron)
     *
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    function bxSyncGoodsGroupsList()
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        $CIBlockSection = new \CIBlockSection;
        $result = Array();
        $mdRes = $this->api->getGoodsGroupsList();

        if ($mdRes['response']) {

            // Prepare ids
            $mdItemsIDs = $toUpdate = Array();
            foreach ($mdRes['response']['ResourceList'] as $item) {
                $mdItemsIDs[$item['Id']] = $item['Id'];
            }
            $arFilter = Array('IBLOCK_ID' => $this->ibCatalogId, 'CHECK_PERMISSIONS' => 'N');
            $arSelect = Array('ID', 'NAME', 'IBLOCK_SECTION_ID', $this->goodCategoryMdIdUfCode);
            $dbRes = $CIBlockSection->GetList(arOrder, $arFilter, arGroupBy, $arSelect, arNav);
            while ($item = $dbRes->fetch()) {
                $sectionMatching[$item[$this->goodCategoryMdIdUfCode]] = $item['IBLOCK_SECTION_ID'];
                if (in_array($item[$this->goodCategoryMdIdUfCode], $mdItemsIDs)) {
                    $toUpdate[$item[$this->goodCategoryMdIdUfCode]] = $item['ID'];
                } else {
                    $toDisable[$item['ID']] = $item['ID'];
                }
            }
            // Add and update
            foreach ($mdRes['response']['ResourceList'] as $mdItem) {
                $arItem = Array(
                    'SITE_ID' => 1,
                    'IBLOCK_ID' => $this->ibCatalogId,
                    'ACTIVE' => 'Y',
                    'NAME' => $mdItem['Name'],
                    'MODIFIED_BY' => $this->modifiedByUserId,
                );
                if (!empty($mdItem['ParentNomenclatureId'])) {
                    $arItem['IBLOCK_SECTION_ID'] = $sectionMatching[$mdItem['ParentNomenclatureId']];
                }
                if ($toUpdate[$mdItem['Id']]) {
                    $op = 'Update';
                    $dbRes = $CIBlockSection->Update($toUpdate[$mdItem['Id']], $arItem);
                    $resID = $toUpdate[$mdItem['Id']];
                } else {
                    $arItem[$this->goodCategoryMdIdUfCode] = $mdItem['Id'];
                    $arItem['CREATED_BY'] = $this->modifiedByUserId;
                    $op = 'Add';
                    $dbRes = $CIBlockSection->Add($arItem);
                    $resID = $dbRes;
                }
                if ($dbRes) {
                    $msg = $op . ': ' . $mdItem['Id'] . ' -> ' . $resID;
                } else {
                    $msg = 'Error: ' . $dbRes->getErrorMessages();
                }
                $result['log'][] = $this->log($msg);
            }
            // Disable
            foreach ($toDisable as $disableID) {
                $arItem = Array(
                    'ACTIVE' => 'N',
                    'MODIFIED_BY' => $this->modifiedByUserId,
                );
                $op = 'Remove';
                $dbRes = $CIBlockSection->Update($disableID, $arItem);
                $resID = $disableID;
                if ($dbRes) {
                    $msg = $op . ': ' . $resID;
                } else {
                    $msg = 'Error: ' . $dbRes->getErrorMessages();
                }
                $result['log'][] = $this->log($msg);
            }

        } else {

            if (count($mdRes['errors']) > 0) {
                $result['errors'] = $mdRes['errors'];
            }

        }

        return $result;
    }

    /**
     * Sync goods (for cron)
     *
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    function bxSyncGoodsList()
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        \Bitrix\Main\Loader::includeModule('catalog');
        $CIBlockSection = new \CIBlockSection;
        $CIBlockElement = new \CIBlockElement;
        $CPrice = new \CPrice;
        $CCatalogProduct = new \CCatalogProduct;
        $CCatalogVat = new \CCatalogVat;
        $CCatalogStoreProduct = new \CCatalogStoreProduct;
        $CatalogStore = new \Bitrix\Catalog\StoreTable;

        // Get categories
        $arFilter = Array('IBLOCK_ID' => $this->ibCatalogId, 'CHECK_PERMISSIONS' => 'N');
        $arSelect = Array('ID', 'TITLE', $this->goodCategoryMdIdUfCode);
        $dbRes = $CIBlockSection->GetList(arOrder, $arFilter, arGroupBy, $arSelect, arNav);
        while ($item = $dbRes->fetch()) {
            $CIDs[$item[$this->goodCategoryMdIdUfCode]] = $item['ID'];
        }

        // Get items
        $arItemsMD = $toDisable = Array();
        $arFilter = Array('IBLOCK_ID' => $this->ibCatalogId, 'CHECK_PERMISSIONS' => 'N');
        $arSelect = Array('ID', 'TITLE', $this->goodMdIdPropCode);
        $dbRes = \CIBlockElement::GetList(arOrder, $arFilter, arGroupBy, arNav, $arSelect);
        while ($item = $dbRes->fetch()) {
            $arItemsMD[$item[$this->goodMdIdPropCode . '_VALUE']] = $item['ID'];
            $toDisable[$item[$this->goodMdIdPropCode . '_VALUE']] = $item['ID'];
        }

        // Measures
        $arMeasures = Array();
        $dbRes = \CCatalogMeasure::getList();
        while ($item = $dbRes->Fetch()) {
            $arMeasures[$item['SYMBOL_RUS']] = $item;
        }

        // Vat
        $arVats = Array();
        $arOrder = Array('CSORT' => 'ASC');
        $dbResVat = $CCatalogVat->GetList($arOrder, arFilter, Array());
        while ($item = $dbResVat->Fetch()) {
            $arVats[$item['RATE']] = $item;
        }

        // Get stocks
        $arStocks = Array();
        $dbRes = $CatalogStore::getList(Array(
            'select' => Array('ID', 'TITLE', $this->stockMdIdUfCode),
        ));
        while ($item = $dbRes->fetch()) {
            $arStocks[$item[$this->stockMdIdUfCode]] = $item;
        }

        // Get goods
        $result = Array();
        $page = 1;
        while ($page) {
            $mdRes = $this->api->getGoodsList(Array('pageNo' => $page, 'pageSize' => $this->pageSize));

            if (count($mdRes['response']['ResourceList']) > 0) {

                $page++;
                $MD_Ids = Array();
                // Add and update
                foreach ($mdRes['response']['ResourceList'] as $mdItem) {
                    $MD_Ids[] = $mdItem['Id'];
                    // Prepare
                    $arItem = Array(
                        'SITE_ID' => 1,
                        'IBLOCK_ID' => $this->ibCatalogId,
                        'ACTIVE' => 'Y',
                        'MODIFIED_BY' => $this->modifiedByUserId,
                        'NAME' => $mdItem['Name'],
                        'CODE' => $mdItem['Article'],
                    );
                    if ($CIDs[$mdItem['NomenclatureId']]) {
                        $arItem['IBLOCK_SECTION_ID'] = $CIDs[$mdItem['NomenclatureId']];
                    }

                    $arItemCatalog = Array(
                        'VAT_ID' => $arVats[number_format($mdItem['Nds'], 2, '.', '')]['ID'],
                        'VAT_INCLUDED' => ($mdItem['NdsPositionType'] == '3') ? 'Y' : 'N',
                    );
                    if ($arMeasures[$mdItem['UnitOfMeasurement']]) {
                        $arItemCatalog['MEASURE'] = $arMeasures[$mdItem['UnitOfMeasurement']]['ID'];
                    }
                    // DB work
                    if ($arItemsMD[$mdItem['Id']]) {
                        unset($toDisable[$mdItem['Id']]);
                        $op = 'Update';
                        $dbRes = $CIBlockElement->Update($arItemsMD[$mdItem['Id']], $arItem);
                        $resID = $arItemsMD[$mdItem['Id']];
                    } else {
                        $arProps = Array(
                            $this->goodArticlePropCode => $mdItem['Article'],
                            $this->goodMdIdPropCode => $mdItem['Id'],
                        );
                        $arItem['PROPERTY_VALUES'] = $arProps;
                        $arItem['CREATED_BY'] = $this->modifiedByUserId;
                        $arItem['ADDRESS'] = 'ADDRESS';
                        $isAdd = true;
                        $op = 'Add';
                        $dbRes = $CIBlockElement->Add($arItem);
                        $resID = $dbRes;
                    }
                    if ($dbRes) {
                        $msg = $op . ': ' . $mdItem['Id'] . ' -> ' . $resID;
                        $arItemCatalog['ID'] = $resID;
                        $dbResBasePrice = $CPrice->SetBasePrice($resID, $mdItem['SalePrice'], 'RUB');
                        if ($isAdd) {
                            $dbResCatalog = $CCatalogProduct->Add($arItemCatalog);
                        } else {
                            $dbResCatalog = $CCatalogProduct->Update($arItemCatalog);
                        }
                    } else {
                        $msg = 'Error: ' . $dbRes->getErrorMessages();
                    }
                    $result['log'][] = $this->log($msg);
                }

                // Disable
                foreach ($toDisable as $disableID) {
                    $arItem = Array(
                        'IBLOCK_ID' => $this->ibCatalogId,
                        'ACTIVE' => 'N',
                        'MODIFIED_BY' => $this->modifiedByUserId,
                    );
                    $op = 'Remove';
                    $dbRes = $CIBlockElement->Update($disableID, $arItem);
                    $resID = $disableID;
                    if ($dbRes) {
                        $msg = $op . ': ' . $resID;
                    } else {
                        $msg = 'Error: ' . $dbRes->getErrorMessages();
                    }
                    $result['log'][] = $this->log($msg);
                }

                // Get stock count
                $qParams = Array('Ids' => $MD_Ids);
                $mdStocksInfo = $this->api->getGoodsCountInStocks($qParams);
                $goods = $mdStocksInfo['response']['Data'];
                foreach ($goods as $good) {
                    $goodId = $good['ProductId'];
                    $goodRemains = $good['GoodRemains'];
                    foreach ($goodRemains as $stockInfo) {
                        $arFields = Array(
                            'PRODUCT_ID' => $goodId,
                            'STORE_ID' => $arStocks[$stockInfo['StockId']]['ID'],
                            'AMOUNT' => $stockInfo['Remains'],
                        );
                        $ID = $CCatalogStoreProduct->UpdateFromForm($arFields);
                        if ($ID) {
                            $msg = 'Update (Good in stock): ' . $goodId . ' = ' . $arFields['AMOUNT'] . ' (' . $arStocks[$stockInfo['StockId']]['TITLE'] . ')';
                        } else {
                            $msg = 'Error update (Good in stock): ' . $goodId;
                        }
                        $result['log'][] = $this->log($msg);
                    }
                }

            } else {

                $page = 0;
                if (count($mdRes['errors']) > 0) {
                    $result['errors'] = $mdRes['errors'];
                }

            }
        }
        return $result;
    }

}