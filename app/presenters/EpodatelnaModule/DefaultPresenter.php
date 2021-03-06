<?php

class Epodatelna_DefaultPresenter extends BasePresenter
{

    protected $Epodatelna;
    protected $pdf_output = 0;
    protected $storage;

    public function __construct()
    {
        parent::__construct();
        $this->Epodatelna = new Epodatelna();
    }

    public function startup()
    {
        parent::startup();
        $this->template->original_view = $this->view;
    }
    
    public function actionDefault()
    {
        $this->redirect('nove');
    }

    protected function shutdown($response)
    {

        if ($this->pdf_output == 1 || $this->pdf_output == 2) {

            ob_start();
            $response->send($this->getHttpRequest(), $this->getHttpResponse());
            $content = ob_get_clean();
            if ($content) {

                @ini_set("memory_limit", PDF_MEMORY_LIMIT);

                if ($this->pdf_output == 2) {
                    $content = str_replace("<td", "<td valign='top'", $content);
                    $content = str_replace("Vytištěno dne:", "Vygenerováno dne:", $content);
                    $content = str_replace("Vytiskl: ", "Vygeneroval: ", $content);
                    $content = preg_replace('#<div id="tisk_podpis">.*?</div>#s', '', $content);
                    $content = preg_replace('#<table id="table_top">.*?</table>#s', '', $content);

                    $mpdf = new mPDF('iso-8859-2', 'A4', 9, 'Helvetica');

                    $app_info = Nette\Environment::getVariable('app_info');
                    $app_info = explode("#", $app_info);
                    $app_name = (isset($app_info[2])) ? $app_info[2] : 'OSS Spisová služba v3';
                    $mpdf->SetCreator($app_name);
                    $mpdf->SetAuthor($this->user->getIdentity()->display_name);
                    $mpdf->SetTitle('Spisová služba - Epodatelna - Detail zprávy');

                    $mpdf->defaultheaderfontsize = 10; /* in pts */
                    $mpdf->defaultheaderfontstyle = 'B'; /* blank, B, I, or BI */
                    $mpdf->defaultheaderline = 1;  /* 1 to include line below header/above footer */
                    $mpdf->defaultfooterfontsize = 9; /* in pts */
                    $mpdf->defaultfooterfontstyle = ''; /* blank, B, I, or BI */
                    $mpdf->defaultfooterline = 1;  /* 1 to include line below header/above footer */
                    $mpdf->SetHeader('||' . $this->template->Urad->nazev);
                    $mpdf->SetFooter("{DATE j.n.Y}/" . $this->user->getIdentity()->display_name . "||{PAGENO}/{nb}"); /* defines footer for Odd and Even Pages - placed at Outer margin */

                    $mpdf->WriteHTML($content);

                    $mpdf->Output('dokument.pdf', 'I');
                } else {
                    $content = str_replace("<td", "<td valign='top'", $content);
                    $content = str_replace("Vytištěno dne:", "Vygenerováno dne:", $content);
                    $content = str_replace("Vytiskl: ", "Vygeneroval: ", $content);
                    $content = preg_replace('#<div id="tisk_podpis">.*?</div>#s', '', $content);
                    $content = preg_replace('#<table id="table_top">.*?</table>#s', '', $content);

                    $mpdf = new mPDF('iso-8859-2', 'A4-L', 9, 'Helvetica');

                    $app_info = Nette\Environment::getVariable('app_info');
                    $app_info = explode("#", $app_info);
                    $app_name = (isset($app_info[2])) ? $app_info[2] : 'OSS Spisová služba v3';
                    $mpdf->SetCreator($app_name);
                    $mpdf->SetAuthor($this->user->getIdentity()->display_name);
                    $mpdf->SetTitle('Spisová služba - Tisk');

                    $mpdf->defaultheaderfontsize = 10; /* in pts */
                    $mpdf->defaultheaderfontstyle = 'B'; /* blank, B, I, or BI */
                    $mpdf->defaultheaderline = 1;  /* 1 to include line below header/above footer */
                    $mpdf->defaultfooterfontsize = 9; /* in pts */
                    $mpdf->defaultfooterfontstyle = ''; /* blank, B, I, or BI */
                    $mpdf->defaultfooterline = 1;  /* 1 to include line below header/above footer */

                    if ($this->getParameter('typ') == 'odchozi')
                        $header = 'Seznam odchozích zpráv';
                    else if ($this->template->view == 'prichozi')
                        $header = 'Seznam příchozích zpráv';
                    else
                        $header = 'Seznam nových zpráv';
                    $mpdf->SetHeader("$header||{$this->template->Urad->nazev}");
                    $mpdf->SetFooter("{DATE j.n.Y}/" . $this->user->getIdentity()->display_name . "||{PAGENO}/{nb}"); /* defines footer for Odd and Even Pages - placed at Outer margin */

                    $mpdf->WriteHTML($content);

                    $mpdf->Output('spisova_sluzba.pdf', 'I');
                }
            }
        }
    }

    public function renderNove()
    {
        $client_config = Nette\Environment::getVariable('client_config');
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($client_config->nastaveni->pocet_polozek) ? $client_config->nastaveni->pocet_polozek : 20;


        $args = array(
            'where' => array('(ep.stav=1) AND (ep.epodatelna_typ=0)')
        );
        $result = $this->Epodatelna->seznam($args);
        $paginator->itemCount = count($result);

        // Volba vystupu - web/tisk/pdf
        $tisk = $this->getParameter('print');
        $pdf = $this->getParameter('pdfprint');
        if ($tisk) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            //$seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $seznam = $result->fetchAll();
            $this->setView('print');
        } elseif ($pdf) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            $this->pdf_output = 1;
            //$seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $seznam = $result->fetchAll();
            $this->setView('print');
        } else {
            $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $this->setView('seznam');
        }

        $this->template->seznam = $seznam;
    }

    public function renderPrichozi()
    {
        $client_config = Nette\Environment::getVariable('client_config');
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($client_config->nastaveni->pocet_polozek) ? $client_config->nastaveni->pocet_polozek : 20;


        $args = null;
        $args = array(
            'where' => array('(ep.stav>=1) AND (ep.epodatelna_typ=0)')
        );
        $result = $this->Epodatelna->seznam($args);
        $paginator->itemCount = count($result);

        // Volba vystupu - web/tisk/pdf
        $tisk = $this->getParameter('print');
        $pdf = $this->getParameter('pdfprint');
        if ($tisk) {
            $seznam = $result->fetchAll();
            $this->setView('print');
        } elseif ($pdf) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            $this->pdf_output = 1;
            $seznam = $result->fetchAll();
            $this->setView('print');
        } else {
            $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $this->setView('seznam');
        }

        $this->template->seznam = $seznam;
    }

    public function renderOdchozi()
    {
        $client_config = Nette\Environment::getVariable('client_config');
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($client_config->nastaveni->pocet_polozek) ? $client_config->nastaveni->pocet_polozek : 20;


        $args = null;
        $args = [
            'where' => ['ep.epodatelna_typ = 1'],
            'order' => ['doruceno_dne' => 'DESC']
        ];
        $result = $this->Epodatelna->seznam($args);
        $paginator->itemCount = count($result);

        // Volba vystupu - web/tisk/pdf
        $tisk = $this->getParameter('print');
        $pdf = $this->getParameter('pdfprint');
        if ($tisk) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            //$seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $seznam = $result->fetchAll();
            $this->setView('printo');
        } elseif ($pdf) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            $this->pdf_output = 1;
            //$seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $seznam = $result->fetchAll();
            $this->setView('printo');
        } else {
            $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
        }

        $this->template->seznam = $seznam;
        //$this->setView('seznam');
    }

    public function actionDetail()
    {
        $epodatelna_id = $this->getParameter('id', null);
        $zprava = $this->Epodatelna->getInfo($epodatelna_id);

        if ($zprava) {

            $this->template->Zprava = $zprava;

            if ($prilohy = unserialize($zprava->prilohy)) {
                $this->template->Prilohy = $prilohy;
            } else {
                $this->template->Prilohy = null;
            }

            $original = null;
            if (!empty($zprava->email_id)) {
                // Nacteni originalu emailu
                if (!empty($zprava->file_id)) {
                    $original = self::nactiEmail($this->storage, $zprava->file_id);

                    if ($original['signature']['signed'] == 3) {

                        $od = $original['signature']['cert_info']['platnost_od'];
                        $do = $original['signature']['cert_info']['platnost_do'];

                        $original['signature']['log']['aktualne']['date'] = date("d.m.Y H:i:s");
                        $original['signature']['log']['aktualne']['message'] = $original['signature']['status'];
                        $original['signature']['log']['aktualne']['status'] = 0;


                        $doruceno = strtotime($zprava->doruceno_dne);
                        $original['signature']['log']['doruceno']['date'] = date("d.m.Y H:i:s", $doruceno);
                        if ($od <= $doruceno && $doruceno <= $do) {
                            $original['signature']['log']['doruceno']['message'] = "Podpis byl v době doručení platný";
                            $original['signature']['log']['doruceno']['status'] = 1;
                        } else {
                            $original['signature']['log']['doruceno']['message'] = "Podpis nebyl v době doručení platný!";
                            $original['signature']['log']['doruceno']['status'] = 0;
                        }

                        $prijato = strtotime($zprava->prijato_dne);
                        $original['signature']['log']['prijato']['date'] = date("d.m.Y H:i:s", $prijato);
                        if ($od <= $prijato && $prijato <= $do) {
                            $original['signature']['log']['prijato']['message'] = "Podpis byl v době přijetí platný";
                            $original['signature']['log']['prijato']['status'] = 1;
                        } else {
                            $original['signature']['log']['prijato']['message'] = "Podpis nebyl v době přijetí platný!";
                            $original['signature']['log']['prijato']['status'] = 0;
                        }
                    }
                }
            } else if (!empty($zprava->isds_id)) {
                // Nacteni originalu DS
                if (!empty($zprava->file_id)) {
                    $source = self::nactiISDS($this->storage, $zprava->file_id);
                    if ($source) {
                        $original = unserialize($source);
                    } else {
                        $original = null;
                    }
                    if (empty($original->dmAcceptanceTime)) {
                        $this->zkontrolujOdchoziISDS($zprava);
                    }
                }
            } else {
                // zrejme odchozi zprava ven
            }

            if (!empty($zprava->dokument_id)) {
                $Dokument = new Dokument();
                $this->template->Dokument = $Dokument->getInfo($zprava->dokument_id);
            } else {
                $this->template->Dokument = null;
            }

            $this->template->Original = $original;
            $this->template->Identifikator = $this->Epodatelna->identifikator($zprava, $original);
        } else {
            $this->flashMessage('Požadovaná zpráva neexistuje!', 'warning');
            $this->redirect('nove');
        }
    }

    public function renderDetail()
    {
        // Volba vystupu - web/tisk/pdf
        $tisk = $this->getParameter('print');
        $pdf = $this->getParameter('pdfprint');
        if ($tisk) {
            $this->setView('printdetail');
        } elseif ($pdf) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            $this->pdf_output = 2;
            $this->setView('printdetail');
        }        
    }
    
    public function renderOdetail()
    {
        $this->actionDetail();

        // Volba vystupu - web/tisk/pdf
        $tisk = $this->getParameter('print');
        $pdf = $this->getParameter('pdfprint');
        if ($tisk) {
            $this->setView('printdetailo');
        } elseif ($pdf) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            $this->pdf_output = 2;
            $this->setView('printdetailo');
        }
    }

    public function renderZkontrolovat()
    {
        new SeznamStatu($this, 'seznamstatu');
    }

    // Stáhne zprávy ze všech schránek a dá uživateli vědět výsledek

    public function actionZkontrolovatAjax()
    {
        @set_time_limit(120); // z moznych dusledku vetsich poctu polozek je nastaven timeout

        /* $id = $this->getParameter('id',null);
          $typ = substr($id,0,1);
          $index = substr($id,1); */

        $config_data = (new Spisovka\ConfigEpodatelna())->get();
        $result = array();

        $nalezena_aktivni_schranka = 0;

        // kontrola ISDS
        $zkontroluj_isds = 1;
        if (count($config_data['isds']) > 0 && $zkontroluj_isds == 1) {
            foreach ($config_data['isds'] as $index => $isds_config) {
                if ($isds_config['aktivni'] != 1)
                    continue;
                if ($isds_config['podatelna'] && !Orgjednotka::isInOrg($isds_config['podatelna']))
                    continue;

                $nalezena_aktivni_schranka = 1;
                $zprava = $this->zkontrolujISDS($isds_config);
                echo "$zprava<br />";
            }
        }
        // kontrola emailu
        $zkontroluj_email = 1;
        if (count($config_data['email']) > 0 && $zkontroluj_email == 1) {
            foreach ($config_data['email'] as $index => $email_config) {
                if ($email_config['aktivni'] != 1)
                    continue;
                if ($email_config['podatelna'] && !Orgjednotka::isInOrg($email_config['podatelna']))
                    continue;

                $nalezena_aktivni_schranka = 1;
                $result = $this->zkontrolujEmail($email_config);
                if (is_string($result))
                    echo $result . '<br />';
                else if ($result > 0) {
                    echo "Z emailové schránky \"" . $email_config['ucet'] . "\" bylo přijato $result nových zpráv.<br />";
                } else {
                    echo 'Z emailové schránky "' . $email_config['ucet'] . '" nebyly zjištěny žádné nové zprávy.<br />';
                }
            }
        }

        if (!$nalezena_aktivni_schranka)
            echo 'Žádná schránka není definována nebo nastavena jako aktivní.<br />';

        exit;
    }

    public function actionZkontrolovatOdchoziISDS()
    {
        // @set_time_limit(600);   
        $this->zkontrolujOdchoziISDS();
        exit;
    }

    public function actionNactiNoveAjax()
    {
        $SubjektModel = new Subjekt();
        $isds_subjekt_cache = [];
        $email_subjekt_cache = [];

        //$client_config = Environment::getVariable('client_config');
        //$vp = new VisualPaginator($this, 'vp');
        //$paginator = $vp->getPaginator();
        //$paginator->itemsPerPage = 2;// isset($client_config->nastaveni->pocet_polozek)?$client_config->nastaveni->pocet_polozek:20;

        $args = array(
            'where' => array('(ep.stav=0 OR ep.stav=1) AND (ep.epodatelna_typ=0)')
        );
        $result = $this->Epodatelna->seznam($args);
        //$paginator->itemCount = count($result);
        $zpravy = $result->fetchAll(); //$paginator->offset, $paginator->itemsPerPage);

        if (!$zpravy)
            $zpravy = null;
        else
            foreach ($zpravy as $zprava) {

                unset($zprava->identifikator);

                $prilohy = unserialize($zprava->prilohy);
                if ($prilohy !== false)
                    $zprava->prilohy = $prilohy;

                /* neni potreba
                  $identifikator = unserialize($zprava->identifikator);
                  if ( $identifikator ) {
                  $zpravy[ $zprava->id ]->identifikator = $identifikator;
                  $identifikator = null;
                  } */

                $subjekt = new stdClass();
                $subjekt->mesto = '';
                $subjekt->psc = '';
                $subjekt->ulice = '';
                $subjekt->cp = '';
                $subjekt->co = '';
                $subjekt->jmeno = '';
                $subjekt->prijmeni = '';

                $original = null;
                $nalezene_subjekty = null;
                if (!empty($zprava->email_id)) {
                    // Nacteni originalu emailu
                    if (!empty($zprava->file_id)) {
                        $original = self::nactiEmail($this->storage, $zprava->file_id);

                        $subjekt->nazev_subjektu = isset($original['zprava']->from->personal) ? $original['zprava']->from->personal : $zprava->odesilatel;
                        $subjekt->prijmeni = $original['zprava']->from->personal;
                        $subjekt->email = $original['zprava']->from->email;
                        $matches = [];
                        if (preg_match('/^(.*) ([^ ]*)$/', $subjekt->prijmeni, $matches)) {
                            $subjekt->jmeno = $matches[1];
                            $subjekt->prijmeni = $matches[2];
                        }

                        if ($original['signature']['signed'] >= 0) {

                            $subjekt->nazev_subjektu = $original['signature']['cert_info']['organizace'];
                            $subjekt->prijmeni = $original['signature']['cert_info']['jmeno'];
                            if (!empty($original['signature']['cert_info']['email']) && $subjekt->email != $original['signature']['cert_info']['email']) {
                                $subjekt->email = $subjekt->email . "; " . $original['signature']['cert_info']['email'];
                            }
                            $subjekt->ulice = $original['signature']['cert_info']['adresa'];
                        }

                        if (!isset($email_subjekt_cache[$subjekt->email]))
                            $email_subjekt_cache[$subjekt->email] = $SubjektModel->hledat($subjekt, 'email', true);
                        $nalezene_subjekty = $email_subjekt_cache[$subjekt->email];
                    }
                } else if (!empty($zprava->isds_id)) {
                    // Nacteni originalu DS
                    if (!empty($zprava->file_id)) {
                        $file_id = explode("-", $zprava->file_id);
                        $original = self::nactiISDS($this->storage, $file_id[0]);
                        $original = unserialize($original);

                        // odebrat obsah priloh, aby to neotravovalo
                        unset($original->dmDm->dmFiles);

                        $subjekt->id_isds = $original->dmDm->dbIDSender;
                        $subjekt->nazev_subjektu = $original->dmDm->dmSender;
                        $subjekt->type = ISDS_Spisovka::typDS($original->dmDm->dmSenderType);
                        if (isset($original->dmDm->dmSenderAddress)) {
                            $res = ISDS_Spisovka::parseAddress($original->dmDm->dmSenderAddress);
                            foreach ($res as $key => $value)
                                $subjekt->$key = $value;
                        }

                        if (!isset($isds_subjekt_cache[$subjekt->id_isds]))
                            $isds_subjekt_cache[$subjekt->id_isds] = $SubjektModel->hledat($subjekt, 'isds', true);
                        $nalezene_subjekty = $isds_subjekt_cache[$subjekt->id_isds];
                    }
                }

                $zprava->subjekt = ['original' => $subjekt, 'databaze' => $nalezene_subjekty];

                $doruceno_dne = strtotime($zprava->doruceno_dne);
                $zprava->doruceno_dne_datum = date("j.n.Y", $doruceno_dne);
                $zprava->doruceno_dne_cas = date("G:i:s", $doruceno_dne);
                $zprava->odesilatel = htmlspecialchars($zprava->odesilatel);
            }

        // Funkce nekdy loguje varovani, ze vstup neni ve formatu utf-8
        echo @json_encode($zpravy);
        exit;
    }

    protected function zkontrolujISDS($config)
    {
        $isds = new ISDS_Spisovka();

        try {
            $isds->pripojit($config);

            $od = $this->Epodatelna->getLastISDS();
            $do = time() + 7200;

            $user = $this->user->getIdentity();

            $UploadFile = $this->storage;

            $pocet_novych_zprav = 0;
            $zpravy = $isds->seznamPrichozichZprav($od, $do);

            if ($zpravy)
                foreach ($zpravy as $z)
                // kontrola existence v epodatelny
                    if (!$this->Epodatelna->existuje($z->dmID, 'isds')) {
                        // nova zprava, ktera neni nahrana v epodatelne
                        // [P.L.] - cache neni vubec funkcni, neukladaly se tam vysledky
                        // $storage = new FileStorage(CLIENT_DIR .'/temp');
                        // $cache = new Cache($storage); // nebo $cache = Environment::getCache()
                        // if (isset($cache['zkontrolovat_isds_'.$z->dmID])):
                        // $mess = $cache['zkontrolovat_isds_'.$z->dmID];
                        // else:

                        $mess = $isds->prectiZpravu($z->dmID);
                        // endif;
                        //echo "<pre>";
                        /*
                          dmDm = objekt
                          dmDm->dmFiles
                          dmHash = objekt
                          dmQTimestamp = string
                          dmDeliveryTime = 2010-05-11T12:24:13.242+02:00
                          dmAcceptanceTime = 2010-05-11T12:26:53.899+02:00
                          dmMessageStatus = 6
                          dmAttachmentSize = 260

                         */
                        /* foreach( $mess->dmDm->dmFiles->dmFile[0] as $k => $m ) {

                          if ( $k == 'dmEncodedContent' ) continue;
                          if ( is_object($m) ) {
                          echo $k ." = objekt\n";
                          } else {
                          echo $k ." = ". $m ."\n";
                          }


                          } */



                        /*
                          dmID = 342682
                          dbIDSender = hjyaavk
                          dmSender = Město Milotice
                          dmSenderAddress = Kovářská 14/1, 37612 Milotice, CZ
                          dmSenderType = 10
                          dmRecipient = Společnost pro výzkum a podporu OpenSource
                          dmRecipientAddress = 40501 Děčín, CZ
                          dmAmbiguousRecipient =
                          dmSenderOrgUnit =
                          dmSenderOrgUnitNum =
                          dbIDRecipient = pksakua
                          dmRecipientOrgUnit =
                          dmRecipientOrgUnitNum =
                          dmToHands =
                          dmAnnotation = Vaše datová zpráva byla přijata
                          dmRecipientRefNumber = KAV-34/06-ŘKAV/2010
                          dmSenderRefNumber = AB-44656
                          dmRecipientIdent = 0.06.00
                          dmSenderIdent = ZN-161
                          dmLegalTitleLaw =
                          dmLegalTitleYear =
                          dmLegalTitleSect =
                          dmLegalTitlePar =
                          dmLegalTitlePoint =
                          dmPersonalDelivery =
                          dmAllowSubstDelivery =
                          dmFiles = objekt
                         */

                        $annotation = empty($mess->dmDm->dmAnnotation) ? "(Datová zpráva č. " . $mess->dmDm->dmID . ")" : $mess->dmDm->dmAnnotation;

                        $popis = '';
                        $popis .= "ID datové zprávy    : " . $mess->dmDm->dmID . "\n"; // = 342682
                        $popis .= "Věc, předmět zprávy : " . $annotation . "\n"; //  = Vaše datová zpráva byla přijata
                        $popis .= "\n";
                        $popis .= "Číslo jednací odesílatele   : " . $mess->dmDm->dmSenderRefNumber . "\n"; //  = AB-44656
                        $popis .= "Spisová značka odesílatele : " . $mess->dmDm->dmSenderIdent . "\n"; //  = ZN-161
                        $popis .= "Číslo jednací příjemce     : " . $mess->dmDm->dmRecipientRefNumber . "\n"; //  = KAV-34/06-ŘKAV/2010
                        $popis .= "Spisová značka příjemce    : " . $mess->dmDm->dmRecipientIdent . "\n"; //  = 0.06.00
                        $popis .= "\n";
                        $popis .= "Do vlastních rukou? : " . (!empty($mess->dmDm->dmPersonalDelivery) ? "ano" : "ne") . "\n"; //  =
                        $popis .= "Doručeno fikcí?     : " . (!empty($mess->dmDm->dmAllowSubstDelivery) ? "ano" : "ne") . "\n"; //  =
                        $popis .= "Zpráva určena pro   : " . $mess->dmDm->dmToHands . "\n"; //  =
                        $popis .= "\n";
                        $popis .= "Odesílatel:\n";
                        $popis .= "            " . $mess->dmDm->dbIDSender . "\n"; //  = hjyaavk
                        $popis .= "            " . $mess->dmDm->dmSender . "\n"; //  = Město Milotice
                        $popis .= "            " . $mess->dmDm->dmSenderAddress . "\n"; //  = Kovářská 14/1, 37612 Milotice, CZ
                        $popis .= "            " . $mess->dmDm->dmSenderType . " - " . ISDS_Spisovka::typDS($mess->dmDm->dmSenderType) . "\n"; //  = 10
                        $popis .= "            org.jednotka: " . $mess->dmDm->dmSenderOrgUnit . " [" . $mess->dmDm->dmSenderOrgUnitNum . "]\n"; //  =
                        $popis .= "\n";
                        $popis .= "Příjemce:\n";
                        $popis .= "            " . $mess->dmDm->dbIDRecipient . "\n"; //  = pksakua
                        $popis .= "            " . $mess->dmDm->dmRecipient . "\n"; //  = Společnost pro výzkum a podporu OpenSource
                        $popis .= "            " . $mess->dmDm->dmRecipientAddress . "\n"; //  = 40501 Děčín, CZ
                        //$popis .= "Je příjemce ne-OVM povýšený na OVM: ". $mess->dmDm->dmAmbiguousRecipient ."\n";//  =
                        $popis .= "            org.jednotka: " . $mess->dmDm->dmRecipientOrgUnit . " [" . $mess->dmDm->dmRecipientOrgUnitNum . "]\n"; //  =
                        $popis .= "\n";
                        $popis .= "Status: " . $mess->dmMessageStatus . " - " . ISDS_Spisovka::stavZpravy($mess->dmMessageStatus) . "\n";
                        $dt_dodani = strtotime($mess->dmDeliveryTime);
                        $dt_doruceni = strtotime($mess->dmAcceptanceTime);
                        $popis .= "Datum a čas dodání   : " . date("j.n.Y G:i:s", $dt_dodani) . " (" . $mess->dmDeliveryTime . ")\n"; //  =
                        $popis .= "Datum a čas doručení : " . date("j.n.Y G:i:s", $dt_doruceni) . " (" . $mess->dmAcceptanceTime . ")\n"; //  =
                        $popis .= "Přibližná velikost všech příloh : " . $mess->dmAttachmentSize . "kB\n"; //  =
                        //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitleLaw ."\n";//  =
                        //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitleYear ."\n";//  =
                        //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitleSect ."\n";//  =
                        //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitlePar ."\n";//  =
                        //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitlePoint ."\n";//  =

                        $zprava = array();
                        $zprava['poradi'] = $this->Epodatelna->getMax();
                        $zprava['rok'] = date('Y');
                        $zprava['isds_id'] = $z->dmID;
                        $zprava['predmet'] = $annotation;
                        $zprava['popis'] = $popis;
                        $zprava['odesilatel'] = $z->dmSender . ', ' . $z->dmSenderAddress;
                        //$zprava['odesilatel_id'] = $z->dmAnnotation;
                        $zprava['adresat'] = $config['ucet'] . ' [' . $config['idbox'] . ']';
                        $zprava['prijato_dne'] = new DateTime();
                        $zprava['doruceno_dne'] = new DateTime($z->dmAcceptanceTime);
                        $zprava['prijal_kdo'] = $user->id;
                        //$zprava['prijal_info'] = serialize($user->identity);

                        $zprava['sha1_hash'] = '';

                        /*
                          dmEncodedContent = obsah
                          dmMimeType = application/pdf
                          dmFileMetaType = main
                          dmFileGuid =
                          dmUpFileGuid =
                          dmFileDescr = odpoved_OVM.pdf
                          dmFormat =
                         */
                        $prilohy = array();
                        if (isset($mess->dmDm->dmFiles->dmFile)) {
                            foreach ($mess->dmDm->dmFiles->dmFile as $index => $file) {
                                $prilohy[] = array(
                                    'name' => $file->dmFileDescr,
                                    'size' => strlen($file->dmEncodedContent),
                                    'mimetype' => $file->dmMimeType,
                                    'id' => $index
                                );
                            }
                        }
                        $zprava['prilohy'] = serialize($prilohy);

                        //$zprava['evidence'] = $z->dmAnnotation;
                        //$zprava['dokument_id'] = $z->dmAnnotation;
                        $zprava['stav'] = 0;
                        $zprava['stav_info'] = '';

                        //print_r($zprava);
                        //exit;

                        if ($epod_id = $this->Epodatelna->insert($zprava)) {

                            /* Ulozeni podepsane ISDS zpravy */
                            $data = array(
                                'filename' => 'ep_isds_' . $epod_id . '.zfo',
                                'dir' => 'EP-I-' . sprintf('%06d', $zprava['poradi']) . '-' . $zprava['rok'],
                                'typ' => '5',
                                'popis' => 'Podepsaný originál ISDS zprávy z epodatelny ' . $zprava['poradi'] . '-' . $zprava['rok']
                                    //'popis'=>'Emailová zpráva'
                            );

                            $signedmess = $isds->SignedMessageDownload($z->dmID);

                            if ($file_o = $UploadFile->uploadEpodatelna($signedmess, $data)) {
                                // ok
                            } else {
                                $zprava['stav_info'] = 'Originál zprávy se nepodařilo uložit';
                                // false
                            }

                            /* Ulozeni reprezentace zpravy */
                            $data = array(
                                'filename' => 'ep_isds_' . $epod_id . '.bsr',
                                'dir' => 'EP-I-' . sprintf('%06d', $zprava['poradi']) . '-' . $zprava['rok'],
                                'typ' => '5',
                                'popis' => 'Byte-stream reprezentace ISDS zprávy z epodatelny ' . $zprava['poradi'] . '-' . $zprava['rok']
                                    //'popis'=>'Emailová zpráva'
                            );

                            if ($file = $UploadFile->uploadEpodatelna(serialize($mess), $data)) {
                                // ok
                                $zprava['stav_info'] = 'Zpráva byla uložena';
                                $zprava['file_id'] = $file->id;
                                $this->Epodatelna->update(
                                        array('stav' => 1,
                                    'stav_info' => $zprava['stav_info'],
                                    'file_id' => $file->id,
                                        ), array(array('id=%i', $epod_id))
                                );
                            } else {
                                // toto se nikam neulozi!
                                $zprava['stav_info'] = 'Reprezentace zprávy se nepodařilo uložit';
                                // false
                            }
                        } else {
                            // a toto rovnez ne
                            $zprava['stav_info'] = 'Zprávu se nepodařilo uložit';
                        }

                        $pocet_novych_zprav++;
                        unset($zprava);
                    }

            if ($pocet_novych_zprav)
                return "Z ISDS schránky \"{$config['ucet']}\" bylo přijato $pocet_novych_zprav nových zpráv.";

            return "Z ISDS schránky \"{$config['ucet']}\" nebyly zjištěny žádné nové zprávy.";
        } catch (Exception $e) {
            return "Při kontrole schránky \"{$config['ucet']}\" došlo k chybě: " . $e->getMessage();
        }
    }

    public function zkontrolujOdchoziISDS($zprava = null)
    {
        $ep_zpravy = array();
        $now = getdate();
        $od = mktime(0, 0, 0, $now['mon'], $now['mday'] - 1, $now['year']);
        $do = mktime(0, 0, 0, $now['mon'], $now['mday'] + 1, $now['year']);

        if (is_null($zprava)) {
            // Nacti zpravy, ktere nemaji datum doruceni
            $args = array(
                'where' => array('ep.epodatelna_typ=1', 'ep.isds_id IS NOT NULL', 'ep.prijato_dne=ep.doruceno_dne')
            );
            $epod = $this->Epodatelna->seznam($args)->fetchAll();
            if (count($epod) > 0) {
                foreach ($epod as $zprava) {
                    $datum = strtotime($zprava->prijato_dne);
                    if ($od > ($datum - 36000))
                        $od = $datum - 36000;
                    if ($do < ($datum + 36000))
                        $do = $datum + 36000;

                    $ep_zpravy[$zprava->isds_id] = array(
                        'id_mess' => $zprava->isds_id,
                        'epodatelna_id' => $zprava->id,
                        'datum_odeslani' => $zprava->prijato_dne,
                        'datum_doruceni' => $zprava->doruceno_dne,
                        'poradi' => $zprava->poradi,
                        'rok' => $zprava->rok
                    );
                }
            }
        } else {
            $datum = strtotime($zprava->prijato_dne);
            $od = $datum - 36000;
            $do = $datum + 36000;

            $ep_zpravy[$zprava->isds_id] = array(
                'id_mess' => $zprava->isds_id,
                'epodatelna_id' => $zprava->id,
                'datum_odeslani' => $zprava->prijato_dne,
                'datum_doruceni' => $zprava->doruceno_dne,
                'poradi' => $zprava->poradi,
                'rok' => $zprava->rok
            );
        }

        if (count($ep_zpravy) == 0)
            return false; // neni co kontrolovat        

        $config_data = (new Spisovka\ConfigEpodatelna())->get();
        $config = $config_data['isds'][0];

        $isds = new ISDS_Spisovka();

        try {
            $isds->pripojit($config);
        } catch (Exception $e) {
            $this->flashMessage('Nepodařilo se připojit k ISDS schránce "' . $config['ucet'] . '"!
                                  ISDS chyba: ' . $e->getMessage(), 'warning');
            return null;
        }

        $zpravy = $isds->seznamOdeslanychZprav($od, $do);

        if (count($zpravy) > 0) {
            $tmp = array();

            $UploadFile = $this->storage;

            foreach ($zpravy as $mess) {

                if (!isset($ep_zpravy[$mess->dmID]))
                    continue;

                $annotation = empty($mess->dmAnnotation) ? "(Datová zpráva č. " . $mess->dmID . ")" : $mess->dmAnnotation;

                $popis = '';
                $popis .= "ID datové zprávy    : " . $mess->dmID . "\n"; // = 342682
                $popis .= "Věc, předmět zprávy : " . $annotation . "\n"; //  = Vaše datová zpráva byla přijata
                $popis .= "\n";
                $popis .= "Číslo jednací odesílatele   : " . $mess->dmSenderRefNumber . "\n"; //  = AB-44656
                $popis .= "Spisová značka odesílatele : " . $mess->dmSenderIdent . "\n"; //  = ZN-161
                $popis .= "Číslo jednací příjemce     : " . $mess->dmRecipientRefNumber . "\n"; //  = KAV-34/06-ŘKAV/2010
                $popis .= "Spisová značka příjemce    : " . $mess->dmRecipientIdent . "\n"; //  = 0.06.00
                $popis .= "\n";
                $popis .= "Do vlastních rukou? : " . (!empty($mess->dmPersonalDelivery) ? "ano" : "ne") . "\n"; //  =
                $popis .= "Doručeno fikcí?     : " . (!empty($mess->dmAllowSubstDelivery) ? "ano" : "ne") . "\n"; //  =
                $popis .= "Zpráva určena pro   : " . $mess->dmToHands . "\n"; //  =
                $popis .= "\n";
                $popis .= "Odesílatel:\n";
                $popis .= "            " . $mess->dbIDSender . "\n"; //  = hjyaavk
                $popis .= "            " . $mess->dmSender . "\n"; //  = Město Milotice
                $popis .= "            " . $mess->dmSenderAddress . "\n"; //  = Kovářská 14/1, 37612 Milotice, CZ
                $popis .= "            " . $mess->dmSenderType . " - " . ISDS_Spisovka::typDS($mess->dmSenderType) . "\n"; //  = 10
                $popis .= "            org.jednotka: " . $mess->dmSenderOrgUnit . " [" . $mess->dmSenderOrgUnitNum . "]\n"; //  =
                $popis .= "\n";
                $popis .= "Příjemce:\n";
                $popis .= "            " . $mess->dbIDRecipient . "\n"; //  = pksakua
                $popis .= "            " . $mess->dmRecipient . "\n"; //  = Společnost pro výzkum a podporu OpenSource
                $popis .= "            " . $mess->dmRecipientAddress . "\n"; //  = 40501 Děčín, CZ
                $popis .= "            org.jednotka: " . $mess->dmRecipientOrgUnit . " [" . $mess->dmRecipientOrgUnitNum . "]\n"; //  =
                $popis .= "\n";
                $popis .= "Status: " . $mess->dmMessageStatus . " - " . ISDS_Spisovka::stavZpravy($mess->dmMessageStatus) . "\n";
                $dt_dodani = strtotime($mess->dmDeliveryTime);
                $dt_doruceni = strtotime($mess->dmAcceptanceTime);
                $popis .= "Datum a čas dodání   : " . date("j.n.Y G:i:s", $dt_dodani) . " (" . $mess->dmDeliveryTime . ")\n"; //  =
                if ($dt_doruceni == 0) {
                    $popis .= "Datum a čas doručení : (příjemce zprávu zatím nepřijal)\n"; //  =    
                } else {
                    $popis .= "Datum a čas doručení : " . date("j.n.Y G:i:s", $dt_doruceni) . " (" . $mess->dmAcceptanceTime . ")\n"; //  =                    
                }
                $popis .= "Přibližná velikost všech příloh : " . $mess->dmAttachmentSize . "kB\n"; //  =

                $zprava = array();
                $zprava['popis'] = $popis;
                if (!empty($mess->dmAcceptanceTime)) {
                    $zprava['doruceno_dne'] = new DateTime($mess->dmAcceptanceTime);
                }
                $zprava['sha1_hash'] = '';

                $epod_id = $ep_zpravy[$mess->dmID]['epodatelna_id'];
                $this->Epodatelna->update($zprava, array(array('id=%i', $epod_id)));

                /* Ulozeni podepsane ISDS zpravy */
                $data = array(
                    'filename' => 'ep_isds_' . $epod_id . '.zfo',
                    'dir' => 'EP-O-' . sprintf('%06d', $ep_zpravy[$mess->dmID]['poradi']) . '-' . $ep_zpravy[$mess->dmID]['rok'],
                    'typ' => '5',
                    'popis' => 'Podepsaný originál ISDS zprávy z epodatelny ' . $ep_zpravy[$mess->dmID]['poradi'] . '-' . $ep_zpravy[$mess->dmID]['rok']
                );

                $signedmess = $isds->SignedSentMessageDownload($mess->dmID);

                if ($file_o = $UploadFile->uploadEpodatelna($signedmess, $data)) {
                    // ok
                } else {
                    $zprava['stav_info'] = 'Originál zprávy se nepodařilo uložit';
                    // false
                }

                /* Ulozeni reprezentace zpravy */
                $data = array(
                    'filename' => 'ep_isds_' . $epod_id . '.bsr',
                    'dir' => 'EP-O-' . sprintf('%06d', $ep_zpravy[$mess->dmID]['poradi']) . '-' . $ep_zpravy[$mess->dmID]['rok'],
                    'typ' => '5',
                    'popis' => 'Byte-stream reprezentace ISDS zprávy z epodatelny ' . $ep_zpravy[$mess->dmID]['poradi'] . '-' . $ep_zpravy[$mess->dmID]['rok']
                );

                if ($file = $UploadFile->uploadEpodatelna(serialize($mess), $data)) {
                    // ok
                    $zprava['stav_info'] = 'Zpráva byla uložena';
                    $zprava['file_id'] = $file->id;
                    $this->Epodatelna->update(
                            array('stav' => 1,
                        'stav_info' => $zprava['stav_info'],
                        'file_id' => $file->id,
                            ), array(array('id=%i', $epod_id))
                    );
                } else {
                    $zprava['stav_info'] = 'Reprezentace zprávy se nepodařilo uložit';
                    // false
                }

                $tmp[] = $zprava;
                unset($zprava);
                //break;
            }
        }

        return ( count($tmp) > 0 ) ? $tmp : null;
    }

    // Vrátí počet nových zpráv nebo řetězec s popisem chyby

    private function zkontrolujEmail($config)
    {
        $imap = new ImapClient();
        $email_mailbox = '{' . $config['server'] . ':' . $config['port'] . '' . $config['typ'] . '}' . $config['inbox'];

        $success = $imap->connect($email_mailbox, $config['login'], $config['password']);
        if (!$success) {
            $msg = 'Nepodařilo se připojit k emailové schránce "' . $config['ucet'] . '"!<br />
                    IMAP chyba: ' . $imap->error();
            return $msg;
        }

        if (!$imap->count_messages()) {
            //  nejsou žádné zprávy k přijetí
            $imap->close();
            return 0;
        }

        $tmp = array();
        $user = $this->user->getIdentity();

        $UploadFile = $this->storage;

        $zpravy = $imap->get_head_messages();

        foreach ($zpravy as $z) {
            // kontrola existence v epodatelny
            if (!$this->Epodatelna->existuje($z->message_id, 'email')) {
                // nova zprava, ktera neni nahrana v epodatelne
                // Nejprve uvolni pamet predchozi zpravy
                $mess = null;

                // Nacteni kompletni zpravy
                $mess = $imap->get_message($z->id_part);

                $popis = '';
                $byla_plain_cast = false;

                foreach ($mess->texts as $zpr) {
                    if ($zpr->subtype == "PLAIN") {
                        $byla_plain_cast = true;
                        $popis .= htmlspecialchars($zpr->text_convert) . "\n";
                    }
                    // Pozn.: standardne je v mailu plain cast a hned za ni HTML cast
                    if ($zpr->subtype == "HTML" && !$byla_plain_cast) {
                        $zpr->text_convert = str_ireplace("<br>", "\n", $zpr->text_convert);
                        $zpr->text_convert = str_ireplace("<br />", "\n", $zpr->text_convert);
                        $popis .= htmlspecialchars($zpr->text_convert) . "\n";
                    }
                }
                if (strlen($popis) > 10000)
                    $popis = substr($popis, 0, 10000);

                if (empty($z->from_address)) {
                    $predmet = empty($z->subject) ? "[Bez předmětu] Emailová zpráva" : $z->subject;
                } else {
                    $predmet = empty($z->subject) ? "[Bez předmětu] Emailová zpráva od " . $z->from_address : $z->subject;
                }


                $zprava = array();
                $zprava['poradi'] = $this->Epodatelna->getMax();
                $zprava['rok'] = date('Y');
                $zprava['email_id'] = $z->message_id;
                $zprava['predmet'] = $predmet;
                $zprava['popis'] = $popis;
                $zprava['odesilatel'] = $z->from_address;
                //$zprava['odesilatel_id'] = $z->dmAnnotation;
                $zprava['adresat'] = $config['ucet'] . ' [' . $config['login'] . ']';
                $zprava['prijato_dne'] = new DateTime();
                $zprava['doruceno_dne'] = new DateTime(date('Y-m-d H:i:s', $z->udate));
                $zprava['prijal_kdo'] = $user->id;
                //$zprava['prijal_info'] = serialize($user->identity);

                $zprava['sha1_hash'] = sha1($mess->source);

                $prilohy = array();
                if (isset($mess->attachments)) {
                    foreach ($mess->attachments as $pr) {
                        $prilohy[] = array(
                            'name' => $pr->filename,
                            'size' => $pr->size,
                            'mimetype' => FileModel::mimeType($pr->filename),
                            'id' => $pr->id_part
                        );
                    }
                }
                $zprava['prilohy'] = serialize($prilohy);

                //$zprava['evidence'] = $z->dmAnnotation;
                //$zprava['dokument_id'] = $z->dmAnnotation;
                $zprava['stav'] = 0;
                $zprava['stav_info'] = '';
                //$zprava['source'] = $z;
                //unset($mess->source);
                //$zprava['source'] = $mess;
                $zprava['file_id'] = null;

                // Test na dostupnost epodpisu
                if ($config['only_signature'] == true) {
                    // pouze podepsane - obsahuje el.podpis
                    if (count($mess->signature) > 0) {
                        if ($config['qual_signature'] == true) {
                            // pouze kvalifikovane
                            $esign = new esignature();
                            $esign->setCACert(LIBS_DIR . '/email/ca_certifikaty');
                            $tmp_email = CLIENT_DIR . '/temp/emailtest_' . sha1($mess->message_id) . '.tmp';
                            file_put_contents($tmp_email, $mess->source);
                            $esign_cert = null;
                            $esign_status = null;
                            $esigned = $esign->verifySignature($tmp_email, $esign_cert, $esign_status);
                            if (@$esigned['cert_info']['CA_is_qualified'] == 1) {
                                // obsahuje - pokracujeme
                            } else {
                                // neobsahuje kvalifikovany epodpis
                                $zprava['stav'] = 100;
                                $zprava['stav_info'] = 'Emailová zpráva byla odmítnuta. Neobsahuje kvalifikovaný elektronický podpis';
                                $this->Epodatelna->insert($zprava);
                                continue;
                            }
                        }
                    } else {
                        // email neobsahuje epodpis
                        $zprava['stav'] = 100;
                        $zprava['stav_info'] = 'Emailová zpráva byla odmítnuta. Neobsahuje elektronický podpis.';
                        $this->Epodatelna->insert($zprava);
                        continue;
                    }
                }

                if ($epod_id = $this->Epodatelna->insert($zprava)) {

                    $data = array(
                        'filename' => 'ep_email_' . $epod_id . '.eml',
                        'dir' => 'EP-I-' . sprintf('%06d', $zprava['poradi']) . '-' . $zprava['rok'],
                        'typ' => '5',
                        'popis' => 'Emailová zpráva z epodatelny ' . $zprava['poradi'] . '-' . $zprava['rok']
                            //'popis'=>'Emailová zpráva'
                    );

                    if ($file = $UploadFile->uploadEpodatelna($mess->source, $data)) {
                        // ok
                        $zprava['stav_info'] = 'Zpráva byla uložena';
                        $zprava['file_id'] = $file->id;
                        $this->Epodatelna->update(
                                array('stav' => 1,
                            'stav_info' => $zprava['stav_info'],
                            'file_id' => $file->id
                                ), array(array('id=%i', $epod_id))
                        );
                    } else {
                        $zprava['stav_info'] = 'Originál zprávy se nepodařilo uložit';
                        // false
                    }
                } else {
                    $zprava['stav_info'] = 'Zprávu se nepodařilo uložit';
                }

                $tmp[] = $zprava;
                unset($zprava);
            }
        }

        $imap->close();
        return count($tmp);
    }

    public static function nactiISDS($storage, $file_id)
    {
        $DownloadFile = $storage;

        if (strpos($file_id, "-") !== false) {
            $file_id = reset(explode("-", $file_id));
        }

        $FileModel = new FileModel();
        $file = $FileModel->getInfo($file_id);
        $res = $DownloadFile->download($file, 1);
        if ($res >= 1) {
            return null;
        } else {
            return $res;
        }
    }

    public static function nactiEmail($storage, $file_id, $output = 0)
    {
        $DownloadFile = $storage;

        if (strpos($file_id, "-") !== false) {
            $file_id = reset(explode("-", $file_id));
        }

        $FileModel = new FileModel();
        $file = $FileModel->getInfo($file_id);
        $res = $DownloadFile->download($file, 2);

        if ($output == 1) {
            return $res;
        }

        $tmp = array();
        // Kontrola epodpisu
        $esign = new esignature();
        $esign->setCACert(LIBS_DIR . '/email/ca_certifikaty');
        $esign_cert = null;
        $esign_status = null;
        $esigned = $esign->verifySignature($res, $esign_cert, $esign_status);

        //Nette\Diagnostics\Debugger::dump($esigned); exit;
        $tmp['signature']['cert'] = @$esigned['cert'];
        $tmp['signature']['cert_info'] = @$esigned['cert_info'];
        $tmp['signature']['status'] = @$esigned['status'];
        $tmp['signature']['signed'] = @$esigned['return'];

        //$imap = new ImapClient();
        $imap = new ImapClientFile();

        if ($imap->open($res)) {
            $zprava = $imap->get_head_message(0);
            $tmp['zprava'] = $zprava;
        } else {
            $tmp['zprava'] = null;
        }

        return $tmp;
    }

    public function actionIsdsovereni()
    {
        $this->template->error = 0;
        $this->template->vysledek = "";
        $epodatelna_id = $this->getParameter('id');
        if ($epodatelna_id) {
            $Epodatelna = new Epodatelna();
            $epodatelna_info = $Epodatelna->getInfo($epodatelna_id);

            if ($epodatelna_info) {
                if (!empty($epodatelna_info->file_id)) {
                    $FileModel = new FileModel();
                    $file = $FileModel->select(array(array("nazev=%s", 'ep-isds-' . $epodatelna_id . '.zfo')))->fetch();
                    if ($file) {

                        // Nacteni originalu DS
                        $DownloadFile = $this->storage;
                        $source = $DownloadFile->download($file, 1);

                        if ($source) {

                            $isds = new ISDS_Spisovka();
                            try {
                                $isds->pripojit();
                                if ($isds->AuthenticateMessage($source)) {
                                    $this->template->vysledek = "Datová zpráva byla ověřena a je platná.";
                                } else {
                                    $this->template->error = 4;
                                    $this->template->vysledek = "Datová zpráva byla ověřena, ale není platná!" .
                                            "<br />" .
                                            'ISDS zpráva: ' . $isds->error();
                                }
                            } catch (Exception $e) {
                                $this->template->error = 3;
                                $this->template->vysledek = "Nepodařilo se připojit k ISDS schránce!" .
                                        "<br />" .
                                        'chyba: ' . $e->getMessage();
                            }
                        }
                    }
                }
            } else {
                $this->template->vysledek = "Nebyla nalezena zpráva!";
                $this->template->error = 1;
            }
        } else {
            $this->template->vysledek = "Neplatný parametr!";
            $this->template->error = 1;
        }
    }

}
