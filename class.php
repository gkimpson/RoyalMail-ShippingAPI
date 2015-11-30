<?php

error_reporting(E_ALL); 
ini_set('display_errors', 1);
ini_set('max_execution_time', 360);
ini_set('default_socket_timeout', 120);
ini_set('soap.wsdl_cache_enabled',1);
ini_set('soap.wsdl_cache_ttl',1);


class RoyalMailLabelRequest
{
    private $api_application_id = '012345678';
    private $api_password = 'password1234!'; 
    private $api_username = 'youremail@mail.comAPI';
    private $api_certificate_passphrase = 'BCDEFGH$%&';
    private $location = 'https://api.royalmail.com/shipping/onboarding'; //live 'https://api.royalmail.com/shipping'

    public $api_service_enhancements = ""; #ServiceEnhancements // 1, 2, 3, 4, 5, 6, 11, 12, 13, 14, 15, 16, 22, 24
    public $api_service_type = ""; #ServiceType // 1,2,D,H,I,R,T // was 'D'
    public $api_service_code = ""; #ServiceOfferings was 'SD1' // RM1-9, SD1-6, STL, TPL, TPN, TPS, TRM, TRN, TSN, TSS, WE1, WE3, WG1, WG3, WG4, WG6, WW1, WW3, WW4, WW6, ZC1
    public $api_service_format = ""; #ServiceFormat // F, L, N, P, E, G, N, P

    public $errors = array();
    public $warnings = array();

    /**
     * prepare SOAP Client request
     * @return object soapclient
     */
    private function prepareRequest()
    {
        // PASSWORD DIGEST
        $time = gmdate('Y-m-d\TH:i:s');
        $created = gmdate('Y-m-d\TH:i:s\Z');
        $nonce = mt_rand();
        $nonce_date_pwd = pack("A*",$nonce) . pack("A*",$created) . pack("H*", sha1($this->api_password));
        $passwordDigest = base64_encode(pack('H*',sha1($nonce_date_pwd)));
        $ENCODEDNONCE = base64_encode($nonce);

        // SET CONNECTION DETAILS
        $soapclient_options = array(); 
        $soapclient_options['cache_wsdl'] = 'WSDL_CACHE_NONE';         
        //$soapclient_options['local_cert'] = '/etc/ssl/certs/certificates/royalmail/shippingv2/bundle.pem';    // dirname(__FILE__) . "\MYPATH\bundle.pem";
        $soapclient_options['local_cert'] = '../ssl/bundle.pem';
        $soapclient_options['passphrase'] = $this->api_certificate_passphrase;
        $soapclient_options['trace'] = true;
        $soapclient_options['ssl_method'] = 'SOAP_SSL_METHOD_SSLv3'; // SOAP_SSL_METHOD_TLS
        $soapclient_options['location'] = $this->location;
        $soapclient_options['soap_version'] = SOAP_1_1;
        $soapclient_options['exceptions'] = 0;
        // $soapclient_options['stream_context'] = stream_context_create(
        //                                             array('http'=> array(
        //                                                 'protocol_version'=>'1.0',
        //                                                 'header' => 'Connection: Close'
        //                                             )));    
        //                                            

        // launch soap client
        try {  
            $client = new SoapClient('royalmail/royalmail/ShippingAPI_V2_0_8.wsdl', $soapclient_options);
            $client->__setLocation($soapclient_options['location']);   
        } catch (SoapFault $e) {  
            echo $e->faultstring;
        }      

        $HeaderObjectXML  = '<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
                              xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
                   <wsse:UsernameToken wsu:Id="UsernameToken-000">
                      <wsse:Username>'.$this->api_username.'</wsse:Username>
                      <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">'.$passwordDigest.'</wsse:Password>
                      <wsse:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">'.$ENCODEDNONCE.'</wsse:Nonce>
                      <wsu:Created>'.$created.'</wsu:Created>
                   </wsse:UsernameToken>
               </wsse:Security>';      

        // push the header into soap
        $HeaderObject = new SoapVar( $HeaderObjectXML, XSD_ANYXML );
        
        // push soap header
        $header = new SoapHeader( 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd', 'Security', $HeaderObject );
        $client->__setSoapHeaders($header);
        return $client;

    } // ef

    /**
     * [CreateShipment creates the shipment request using the supplied data]
     * @param array $data shipment details
     * @param boolean $is_international
     */
    public function CreateShipment($data, $is_international = false)
    {
        $request = (!$is_international) ? $this->buildCreateShipment($data) : $this->buildCreateInternationalShipment($data);
        $type    = 'createShipment';

        var_dump($request);exit;
        return $this->makeRequest($type, $request);
    } // ef

    private function buildCreateInternationalShipment($shipment_data) 
    {
        $time = gmdate('Y-m-d\TH:i:s');
        $data = new ArrayObject();
        foreach ($shipment_data as $key => $value)
        {
            $data->$key = $value;
        }

        $request = array(
            'integrationHeader' => array(
                'dateTime' => $time,
                'version' => '2',
                'identification' => array(
                    'applicationId' => $this->api_application_id,
                    'transactionId' => $data->order_tracking_id
                )
            ),
            'requestedShipment' => array(
                'shipmentType' => array('code' => 'Delivery'),
                'serviceOccurrence' => 1,
                'serviceType' => array('code' => $this->api_service_type),
                'serviceOffering' => array('serviceOfferingCode' => array('code' => $this->api_service_code)),
                'serviceFormat' => array('serviceFormatCode' => array('code' => $this->api_service_format)),
                'shippingDate' => date('Y-m-d'),
                'recipientContact' => array(
                    'name' => $data->shipping_name, 
                    'complementaryName' => $data->shipping_company,
                    'telephoneNumber' => array('countryCode' => $data->telephone_number_country_code, 'telephoneNumber' => $data->telephone_number),
                    'electronicAddress' => array('electronicAddress' => $data->shipping_email_address)),
                'recipientAddress' => array('addressLine1' => $data->shipping_address1, 'addressLine2' => $data->shipping_address2, 'postTown' => $data->shipping_town, 'postcode' => $data->shipping_postcode, 'country' => array('countryCode' => array('code' => $data->shipping_country_code))),
                'senderReference' => (isset($data->sender_reference)) ? $data->sender_reference : '',
                'customerReference' => (isset($data->customer_reference)) ? $data->customer_reference : '',
                'internationalInfo' => array('parcels' => 
                                    array('parcel' => 
                                        array('weight' => array('unitOfMeasure' => array('unitOfMeasureCode' => array('code' => 'g')), 'value' => '500')),
                                        array('length' => array('unitOfMeasure' => array('unitOfMeasureCode' => array('code' => 'cm')), 'value' => '1')),
                                        array('height' => array('unitOfMeasure' => array('unitOfMeasureCode' => array('code' => 'cm')), 'value' => '1')),
                                        array('width' => array('unitOfMeasure' => array('unitOfMeasureCode' => array('code' => 'cm')), 'value' => '1')),
                                        array('purposeOfShipment' => array('code' => 'O')), // “G” for Gift “S” for Commercial Sample "D" for Documents "R" for Returned Goods "O" for Other
                                        array('invoiceNumber' => 'INV001'),
                                        array('contentDetails' => array(
                                            //'contentDetail' => array('countryOfManufacture' => array('code' => 'GB')),
                                            'contentDetail' => array('countryOfManufacture' => array('countryCode' => array('code' => $data->shipping_country_code))),
                                            'manufacturersName' => 'Tissot',
                                            'description' => 'Personal Effects',
                                            'unitWeight' => array('unitOfMeasure' => array('unitOfMeasureCode' => array('code' => 'g')), 'value' => 503),
                                            'unitQuantity' => '1',
                                            'unitValue' => '500',
                                            'currencyCode' => array('code' => 'GBP'),
                                            'tariffCode' => array('code' => 'tarCode1'),
                                            'tariffDescription' => array('code' => 'tarDesc1'),
                                            'articleReference' => '1'
                                            )),
                                        ),
                            'invoiceDate' => date('Y-m-d'),
                            'termsOfDelivery' => 'EXW',
                            'purchaseOrderRef' => 'PURCH1'
                    ),
            ) // end requestedShipment array
        );

        return $request;
    }    

    /**
     * [PrintLabel prints label using the supplied shipment number & order tracking id (transactionId)]
     * @param string $shipmentNumber    shipmentNumber in createShipment response
     * @param string $order_tracking_id transactionId in createShipment response (This is a unique number used to identify the transaction as provided by the customer system. Any value can be provided in this field but must contain only the characters ‘a-z’, ‘A-Z’, ‘0-9’, ‘/’ and ‘-‘. It allows the consuming application to correlate the response message to its request.)
     * @param string $outputFormat      PDF, DS, DSPDF, PNG, DSPNG
     * @return string printed label (base64 encoded)
     */
    public function PrintLabel($shipmentNumber, $order_tracking_id, $outputFormat = 'PDF')
    {
        $time = gmdate('Y-m-d\TH:i:s');
        $request = array(
                'integrationHeader' => array(
                    'dateTime' => $time,
                    'version' => '2',
                    'identification' => array(
                        'applicationId' => $this->api_application_id,
                        'transactionId' => $order_tracking_id
                    )
                ),
                'shipmentNumber' => $shipmentNumber,
                'outputFormat' => $outputFormat,
        );

        $type = 'printLabel';

        $response = $this->makeRequest($type, $request);
        
        if (is_object($response) && isset($response->label))
            return $response->label;
    } // ef

    /**
     * [makeRequest make a SOAP request]
     * @param  string $type    createShipment/printLabel etc..
     * @param  object $request SOAP request
     * @return object $response SOAP response
     */
    private function makeRequest($type, $request)
    {
        $client = $this->prepareRequest();
        $response = false;
        $times = 1;

        while(true){

            try {
                $response = $client->__soapCall( $type, array($request), array('soapaction' => $this->location) );
                //echo "REQUEST:\n" . htmlentities($client->__getLastResponse()) . "\n";
                
                    // get errors
                    if (isset($response->integrationFooter->errors->error)) {
                        if (is_array($response->integrationFooter->errors->error)) {
                            foreach ($response->integrationFooter->errors->error as $k => $error) {
                                $this->errors[] = $error->errorCode .' : '. $error->errorDescription;
                            }

                            //echo $csv  = implode('<br>', $this->errors);

                        } elseif (is_object($response->integrationFooter->errors->error)) {
                            $errorCode = $response->integrationFooter->errors->error->errorCode;
                            $errorDescription = $response->integrationFooter->errors->error->errorDescription;
                            $this->errors[] = $errorCode .' : '. $errorDescription;
                        }
                        
                        if (count($this->errors) > 0) {
                            print_r($this->errors);
                            //die('Errors found');
                        }
                    }

                    // get warnings
                    if (isset($response->integrationFooter->warnings->warning)) {
                        if (is_array($response->integrationFooter->warnings->warning)) {
                            foreach ($response->integrationFooter->warnings->warning as $k => $warning) {
                                $this->warnings[] = $warning->warningCode .' : '. $warning->warningDescription;
                            }
                        } elseif (is_object($response->integrationFooter->warnings->warning)) {                            
                            $warningCode = $response->integrationFooter->warnings->warning->warningCode;
                            $warningDescription = $response->integrationFooter->warnings->warning->warningDescription;
                            $this->warnings[] = $warningCode .' : '. $warningDescription;
                        }
                        
                        if (count($this->warnings) > 0) {
                            //print_r($this->warnings);
                            $this->saveWarningsToDb($this->warnings);
                        }

                    }

                break;

            } catch (Exception $e) {                
                print_r($e);

                if(@$e->detail->exceptionDetails->exceptionCode == "E0010" && $times <= 25) {
                sleep(1.5);                     
                $times++;
                continue;                       

            } else {
                echo $e->getMessage();
                echo "<pre>";
                print_r(@$e->detail);
                //echo $client->__getLastResponse();
                //echo "REQUEST:\n" . htmlentities($client->__getLastResponse()) . "\n";
                break;

                    }           

                }

                break;
            }

            return $response;
    } // ef

    /**
     * [buildCreateShipment attaches the shipment data to the request]
     * @param  array $shipment_data
     * @return object $request
     */
    private function buildCreateShipment($shipment_data)
    {
        $time = gmdate('Y-m-d\TH:i:s');
        $data = new ArrayObject();
        foreach ($shipment_data as $key => $value)
        {
            $data->$key = $value;
        }

        $request = array(
            'integrationHeader' => array(
                'dateTime' => $time,
                'version' => '2',
                'identification' => array(
                    'applicationId' => $this->api_application_id,
                    'transactionId' => $data->order_tracking_id
                )
            ),
            'requestedShipment' => array(
                'shipmentType' => array('code' => 'Delivery'),
                'serviceOccurrence' => 1,
                'serviceType' => array('code' => $this->api_service_type),
                'serviceOffering' => array('serviceOfferingCode' => array('code' => $this->api_service_code)),
                'serviceFormat' => array('serviceFormatCode' => array('code' => $this->api_service_format)),
                'shippingDate' => date('Y-m-d'),
                'recipientContact' => array('name' => $data->shipping_name, 'complementaryName' => $data->shipping_company, 'electronicAddress' => array('electronicAddress' => $data->shipping_email_address)),
                'recipientAddress' => array('addressLine1' => $data->shipping_address1,  'addressLine2' => $data->shipping_address2, 'postTown' => $data->shipping_town, 'postcode' => $data->shipping_postcode),
                'items' => array('item' => array(
                                'numberOfItems' => $data->order_tracking_boxes,
                                'weight' => array( 'unitOfMeasure' => array('unitOfMeasureCode' => array('code' => 'g')),
                                'value' => $data->order_tracking_weight, // ($data->order_tracking_weight * 1000)
                                                )
                                            )
                                ),
                'senderReference' => (isset($data->sender_reference)) ? $data->sender_reference : '',
                'customerReference' => (isset($data->customer_reference)) ? $data->customer_reference : ''
                //'signature' => 0,
            )
        );

        if($this->api_service_enhancements != "")
        {
            $request['requestedShipment']['serviceEnhancements'] = array('enhancementType' => array('serviceEnhancementCode' => array('code' => $this->api_service_enhancements)));
        }

        return $request;

    } // ef

    /**
     * [UpdateShipment description]
     * @param [type] $data           [description]
     * @param [type] $shipmentNumber [description]
     */
    public function UpdateShipment($data, $shipmentNumber)
    {
        $request = $this->buildUpdateShipment($data, $shipmentNumber);
        $type    = 'updateShipment';

        return $this->makeRequest($type, $request);
    } // ef

    /**
     * [buildUpdateShipment description]
     * @param  [type] $shipment_data  [description]
     * @param  [type] $shipmentNumber [description]
     * @return [type]                 [description]
     */
    private function buildUpdateShipment($shipment_data, $shipmentNumber)
    {
        $time = gmdate('Y-m-d\TH:i:s');
        $data = new ArrayObject();
        foreach ($shipment_data as $key => $value)
        {
            $data->$key = $value;
        }

        $request = array(
            'integrationHeader' => array(
                'dateTime' => $time,
                'version' => '2',
                'identification' => array(
                    'applicationId' => $this->api_application_id,
                    'transactionId' => $data->order_tracking_id
                )
            ),
            'shipmentNumber' => $shipmentNumber,
            'requestedShipment' => array(
                'recipientAddress' => array(
                    'addressLine1' => $data->shipping_address1,
                    'addressLine2' => $data->shipping_address2,
                    'postTown' => $data->shipping_town,
                    'postcode' => $data->shipping_postcode,
                    'country' => array(
                        'countryCode' => array('code' => isset($data->shipping_country_code) ? $data->shipping_country_code : 'GB')
                        ),
                    ),
            )
        );

        return $request;

    } // efCancelShipment

    /**
     * [CancelShipment description]
     * @param [type] $data           [description]
     * @param [type] $shipmentNumber [description]
     */
    public function CancelShipment($data, $shipmentNumber)
    {
        $request = $this->buildCancelShipment($data, $shipmentNumber);
        $type    = 'cancelShipment';

        return $this->makeRequest($type, $request);
    } // ef    

    /**
     * [buildCancelShipment description]
     * @param  [type] $shipment_data  [description]
     * @param  [type] $shipmentNumber [description]
     * @return [type]                 [description]
     */
    private function buildCancelShipment($shipment_data, $shipmentNumber)
    {
        $time = gmdate('Y-m-d\TH:i:s');
        $data = new ArrayObject();
        foreach ($shipment_data as $key => $value)
        {
            $data->$key = $value;
        }

        $request = array(
            'integrationHeader' => array(
                'dateTime' => $time,
                'version' => '2',
                'identification' => array(
                    'applicationId' => $this->api_application_id,
                    'transactionId' => $data->order_tracking_id
                )
            ),
            'cancelShipments' => array('shipmentNumber' => $shipmentNumber),
            'shipmentNumber' => $shipmentNumber,
        );

        return $request;
    }

    /**
     * [CreateManifest description]
     * @param [type] $data [description]
     */
    public function CreateManifest($data)
    {
        $request = $this->buildCreateManifest($data);
        $type    = 'createManifest';
        return $this->makeRequest($type, $request);
    }

    /**
     * [buildCreateManifest description]
     * @param  [type] $shipment_data [description]
     * @return [type]                [description]
     */
    private function buildCreateManifest($shipment_data)
    {
        $time = gmdate('Y-m-d\TH:i:s');
        $data = new ArrayObject();
        foreach ($shipment_data as $key => $value)
        {
            $data->$key = $value;
        }

        $request = array(
            'integrationHeader' => array(
                'dateTime' => $time,
                'version' => '2',
                'identification' => array(
                    'applicationId' => $this->api_application_id,
                    'transactionId' => $data->order_tracking_id
                )
            ),
            'yourDescription' => $data->your_description,
            'yourReference'   => $data->your_reference
        );

        return $request;
    }

    /**
     * [PrintManifest description]
     * @param [type] $data [description]
     */
    public function PrintManifest($data)
    {
        $request = $this->buildPrintManifest($data);
        $type    = 'printManifest';
        
        $response = $this->makeRequest($type, $request);
        return $response->manifest;
    }

    /**
     * [buildPrintManifest description]
     * @param  [type] $shipment_data [description]
     * @return [type]                [description]
     */
    private function buildPrintManifest($shipment_data)
    {
        $time = gmdate('Y-m-d\TH:i:s');
        $data = new ArrayObject();
        foreach ($shipment_data as $key => $value)
        {
            $data->$key = $value;
        }

        $request = array(
            'integrationHeader' => array(
                'dateTime' => $time,
                'version' => '2',
                'identification' => array(
                    'applicationId' => $this->api_application_id,
                    'transactionId' => $data->order_tracking_id
                )
            ),
            'manifestBatchNumber' => $data->manifest_batch_number,
        );

        return $request;
    }

    public function saveWarningsToDb($warnings) 
    {
        $csv  = implode(', ', $warnings);
        $sql    = 'INSERT INTO royalmail_warnings (description) VALUES (?)';
        $q      = $GLOBALS['dbh']->prepare($sql);
        $result = $q->execute(array($csv));

        if ($result == false) {
            throw new Exception('Unable to save warnings to database.');
        }
    }

} // end class