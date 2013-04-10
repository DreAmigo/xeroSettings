<?php

    require_once EXTENSIONS . '/xero/libs/xero.php';

    class XeroSettings {

        /**
         * http://developer.xero.com/api/types/
         */
        const GST_EXEMPT = "EXEMPTINPUT";

        /**
         * Chart of Accounts
         *
         * @see http://blog.xero.com/developer/api/accounts/
         *
         */
        const DONCOST  = "00001";

        /**
         * Chart of Accounts
         */
        const DONREV = "00002";

        /**
         * Code for the Inventory Item.
         *
         * @see http://blog.xero.com/developer/api/items/
         */
        const COMMISSION_INVENTORY_ITEM = "CODE";

        /**
         * Default Currency.
         */
        const DEFAULT_CURRENCY = "AUD";


        /**
         * Connect to Xero.
         *
         * @return Xero
         */
        public function connect() {
            $XERO_KEY = Symphony::Configuration()->get('xero-key', 'xero');
            $XERO_SECRET = Symphony::Configuration()->get('xero-secret', 'xero');
            return new Xero($XERO_KEY, $XERO_SECRET, EXTENSIONS . '/xero/certs/publickey.cer', EXTENSIONS . '/xero/certs/privatekey.pem', 'xml' );
        }

        /**
         *
         * This function will create/update a new Contact in Xero.
         *
         * @see http://blog.xero.com/developer/api/contacts/
         *
         * @param type $contactId
         * @param array $data
         * @return array
         */
        public function contact ($contactId, array $data) {

            $xero = $this->connect();

            $contact = $xero->Contacts($contactId);

            $response = (string) $contact->Status;

            $newContact = array(
                'Contact' => array(
                    'Name' => $data['name'],
                    'FirstName' => $data['firstName'],
                    'EmailAddress' => $data['email'],
                    'TaxNumber' => $data['taxNumber'],
                    'BankAccountDetails' => $data['bankAccount'],
                    'ContactStatus' => 'ACTIVE',
                    'DefaultCurrency' => self::DEFAULT_CURRENCY,
                    "Addresses" => array (
                        "Address" => array(
                            array(
                                // Postal Address
                                "AddressType" => "POBOX",
                                "AddressLine1" => $data['address'],
                                "City" => $data['city'],
                                "Region" => $data['region'],
                                "PostalCode" => $data['postalCode'],
                                "Country" => $data['country'],
                                "AttentionTo" => $data['firstName']
                                ),
                            array(
                                // Physical Addres
                                "AddressType" => "STREET",
                                "AddressLine1" => $data['address'],
                                "City" => $data['city'],
                                "Region" => $data['region'],
                                "PostalCode" => $data['postalCode'],
                                "Country" => $data['country'],
                                "AttentionTo" => $data['firstName']
                            )
                        )
                    ),
                    'Phones' => array(
                        'Phone' => array(
                            'PhoneType' => "DEFAULT",
                            'PhoneNumber' => $data['phone'],
                            'PhoneAreaCode' => "",
                            'PhoneCountryCode' => ""
                        )
                    )
                )
            );

            // Post to Xero.
            $contact = $xero->Contacts($newContact);

            // Get the Response from Xero.
            $response = (string) $contact->Status;

            // New ContactID.
            $contactID = (string) $contact->Contacts->Contact->ContactID;

            return $response;

        }

        /**
         *
         * Create a new inventory item in XERO.
         *
         * @see http://blog.xero.com/developer/api/items/
         *
         * @param type $id
         * @param array $data
         * @return type
         */
        public function newInventoryItem($id, array $data) {

            $newInventoryItem = array(
                'Item' => array(
                    'Code' => $id,
                    'Description' => $data['description'],
                    'PurchaseDetails' => array(
                        'UnitPrice' => "0.00",
                        'TaxType' => self::GST_EXEMPT,
                        'AccountCode' => self::DONCOST
                    ),
                    'SalesDetails' => array(
                        'UnitPrice' => "0.00",
                        'TaxType' => self::GST_EXEMPT,
                        'AccountCode' => self::DONREV
                    )
                )
            );

            $items = $xero->Items($id);

            $response = (string) $items->Status;

            // Create a new Inventory Item.
            $items = $xero->Items($newInventoryItem);
            $response = (string) $items->Status;

            return $response;

        }


        /**
         * This function will create a new Sales Invoice on Xero.
         *
         * @see http://blog.xero.com/developer/api/invoices/
         *
         * @param type $invoice
         */
        public function salesInvoice($contactId, $data) {

           // Set the due date to 30 days.
           $dueDate = strtotime(date("Y-m-d", strtotime(date_format(date('Y-m-d'), 'Y-m-d'))) . " +30 day");

           $saleInvoice = array(
                array(
                    "Type" => "ACCREC",
                    "Contact" => array(
                        "ContactID" => $contactId
                    ),
                    "Date" => date('Y-m-d'),
                    "DueDate" => date('Y-m-d', $dueDate),
                    "InvoiceNumber" => $data['transaction-number'],
                    "Reference" => $data['receipt-number'],
                    "Status" => "DRAFT",
                    "LineAmountTypes" => "Inclusive",
                    "LineItems"=> array(
                        "LineItem" => array(
                            "ItemCode"   => $data['itemCode'],
                            "UnitAmount" => $data["amount"]
                        )
                    )
                )
            );

            $xero = $this->connect();
            $response = $xero->Invoices ($saleInvoice);

            return $response;

        }


        /**
         * This function will create a new Purchase Invoice on Xero.
         *
         * @see http://blog.xero.com/developer/api/invoices/
         *
         * @param type $invoice
         */
        public function purchaseInvoice($accountId, $data) {

            $this->connect();

           // Set the due date to 30 days.
           $dueDate = strtotime(date("Y-m-d", strtotime(date_format(date('Y-m-d'), 'Y-m-d'))) . " +30 day");

            $new_invoice = array(
                array(
                    "Type" => "ACCPAY",
                    "Contact" => array(
                        // Xero Beneficiary ContactID.
                        "ContactID" => $accountId
                    ),
                    "Date" => date('Y-m-d'),
                    "DueDate" => $dueDate,
                    "InvoiceNumber" => $data['invoice-number'],
                    "Status" => "DRAFT",
                    "LineAmountTypes" => "Inclusive",
                    "LineItems"=> array(
                        "LineItem" => array(
                            array(
                                "ItemCode"   => $data['code'],
                                "UnitAmount" => $data["amount"]
                            )
                        )
                    )
                )
            );

            $response = $xero->Invoices ($new_invoice);

            return $response;

        }

    } // end class