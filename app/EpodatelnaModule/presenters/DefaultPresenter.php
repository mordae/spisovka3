<?php

class Epodatelna_DefaultPresenter extends BasePresenter
{

    private $Epodatelna;

    public function actionDefault()
    {
        $this->redirect('nove');
    }

    public function renderNove()
    {

        if ( is_null($this->Epodatelna) ) $this->Epodatelna = new Epodatelna();

        $user_config = Environment::getVariable('user_config');
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($user_config->nastaveni->pocet_polozek)?$user_config->nastaveni->pocet_polozek:20;


        $args = array(
            'where' => array('(ep.stav=0 OR ep.stav=1) AND (ep.epodatelna_typ=0)')
        );
        $result = $this->Epodatelna->seznam($args);
        $paginator->itemCount = count($result);
        $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);

        $this->template->seznam = $seznam;
        $this->setView('seznam');
        
    }

    public function renderPrichozi()
    {

        if ( is_null($this->Epodatelna) ) $this->Epodatelna = new Epodatelna();

        $user_config = Environment::getVariable('user_config');
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($user_config->nastaveni->pocet_polozek)?$user_config->nastaveni->pocet_polozek:20;


        $args = null;
        $args = array(
            'where' => array('ep.epodatelna_typ=0')
        );
        $result = $this->Epodatelna->seznam($args);
        $paginator->itemCount = count($result);
        $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);

        $this->template->seznam = $seznam;
        $this->setView('seznam');

    }

    public function renderOdchozi()
    {

        if ( is_null($this->Epodatelna) ) $this->Epodatelna = new Epodatelna();

        $user_config = Environment::getVariable('user_config');
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($user_config->nastaveni->pocet_polozek)?$user_config->nastaveni->pocet_polozek:20;


        $args = null;
        $args = array(
            'where' => array('ep.epodatelna_typ=1')
        );
        $result = $this->Epodatelna->seznam($args);
        $paginator->itemCount = count($result);
        $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);

        $this->template->seznam = $seznam;
        //$this->setView('seznam');

    }

    public function actionDetail()
    {

        if ( is_null($this->Epodatelna) ) $this->Epodatelna = new Epodatelna();

        $epodatelna_id = $this->getParam('id',null);
        $zprava = $this->Epodatelna->getInfo($epodatelna_id);

        if ( $zprava ) {

            $this->template->Zprava = $zprava;

            if ($prilohy = unserialize($zprava->prilohy) ) {
                $this->template->Prilohy = $prilohy;
            } else {
                $this->template->Prilohy = null;
            }

            $original = null;
            if ( !empty($zprava->email_signature) ) {
                // Nacteni originalu emailu
                if ( !empty( $zprava->source_id ) ) {
                    $original = $this->nactiEmail($zprava->source_id);
                }
            } else if ( !empty($zprava->isds_signature) ) {
                // Nacteni originalu DS
                if ( !empty( $zprava->source_id ) ) {
                    $original = null;// $this->nactiISDS($zprava->source_id);
                }
            } else {
                // zrejme odchozi zprava ven
            }
            $this->template->Original = $original;

        } else {
            $this->flashMessage('Požadovaná zpráva neexistuje!','warning');
            $this->redirect('nove');
        }

    }

    public function actionOdetail()
    {
        $this->actionDetail();
    }

    public function renderZkontrolovat()
    {

        @set_time_limit(300); // z moznych dusletku vetsich poctu polozek je nastaven timeout na 5 minut

        //$storage = new FileStorage(APP_DIR .'/app/temp/cache');
        //$cache = new Cache($storage); // nebo $cache = Environment::getCache()

        $id = $this->getParam('id',null);
        $typ = substr($id,0,1);
        $index = substr($id,1);

        $config = Config::fromFile(APP_DIR .'/configs/'. KLIENT .'_epodatelna.ini');
        $config_data = $config->toArray();
        $result = array();

        if ( !empty($typ) && !empty($index) ) {
            // predan parametr - zkontroluj jen toto
            $typ_array = array('i'=>'isds','e'=>'email');
            if ( array_key_exists($typ,$typ_array) ) {
                $typ = $typ_array[ $typ ];
                if ( isset( $config_data[$typ][$index] ) ) {
                    $config = $config_data[$typ][$index];
                    if ( $typ == 'isds' ) {
                        $result[$typ .'_'. $index] = $this->zkontrolujISDS($config);
                        if ( count($result[$typ .'_'. $index])>0 ) {
                            $this->flashMessage('Z ISDS schránky "'.$config['ucet'].'" bylo přijato '.(count($result[$typ .'_'. $index])).' nových zpráv.');
                        } else {
                            $this->flashMessage('Z ISDS schránky "'.$config['ucet'].'" nebyly zjištěny žádné nové zprávy.');
                        }
                    } else if ( $typ == 'email' ) {
                        $result[$typ .'_'. $index] = $this->zkontrolujEmail($config);
                        if ( count($result[$typ .'_'. $index])>0 ) {
                            $this->flashMessage('Z emailové schránky "'.$config['ucet'].'" bylo přijato '.(count($result[$typ .'_'. $index])).' nových zpráv.');
                        } else {
                            $this->flashMessage('Z emailové schránky "'.$config['ucet'].'" nebyly zjištěny žádné nové zprávy.');
                        }
                    }
                } else {
                    $this->flashMessage('Není možné zkontrolovat schránku. Neexistuje dané nastavení!','warning');
                }
            }
        } else {
            // zkontroluj vse

            // kontrola ISDS
            $zkontroluj_isds = 1;
            if ( count( $config_data['isds'] )>0 && $zkontroluj_isds==1 ) {
                foreach ($config_data['isds'] as $index => $isds_config) {
                    if ( $isds_config['aktivni'] == 1 ) {
                        $result['isds_'.$index] = $this->zkontrolujISDS($isds_config);
                        if ( count($result['isds_'.$index])>0 ) {
                            $this->flashMessage('Z ISDS schránky "'.$isds_config['ucet'].'" bylo přijato '.(count($result['isds_'.$index])).' nových zpráv.');   
                        } else {
                            $this->flashMessage('Z ISDS schránky "'.$isds_config['ucet'].'" nebyly zjištěny žádné nové zprávy.');                                    
                        }
                    }
                }
            }
            // kontrola emailu
            $zkontroluj_email = 1;
            if ( count( $config_data['email'] )>0 && $zkontroluj_email==1 ) {
                foreach ($config_data['email'] as $index => $email_config) {
                    if ( $email_config['aktivni'] == 1 ) {
                        $result['email_'.$index] = $this->zkontrolujEmail($email_config);
                        if ( count($result['email_'.$index])>0 ) {
                            $this->flashMessage('Z emailové schránky "'.$email_config['ucet'].'" bylo přijato '.(count($result['email_'.$index])).' nových zpráv.');
                        } else {
                            $this->flashMessage('Z emailové schránky "'.$email_config['ucet'].'" nebyly zjištěny žádné nové zprávy.');
                        }
                    }
                }
            }
        }


        $this->redirect('nove');
        $this->template->seznam = $result;


    }

    public function zkontrolujISDS($config)
    {

        $isds = new ISDS_Spisovka();

        if ( is_null($this->Epodatelna) ) $this->Epodatelna = new Epodatelna();

        if ( $ISDSBox = $isds->pripojit($config) ) {
            $zpravy = $isds->seznamPrichozichZprav();
            if ( count( $zpravy )>0 ) {
                $tmp = array();
                $user = Environment::getUser()->getIdentity();

                $storage_conf = Environment::getConfig('storage');
                eval("\$UploadFile = new ".$storage_conf->type."();");

                foreach($zpravy as $z) {
                    // kontrola existence v epodatelny
                    if ( ! $this->Epodatelna->existuje($z->dmID ,'isds') ) {
                        // nova zprava, ktera neni nahrana v epodatelne

                        // rozparsovat do jednotne podoby
                        $storage = new FileStorage(APP_DIR .'/app/temp/cache');
                        $cache = new Cache($storage); // nebo $cache = Environment::getCache()
                        if (isset($cache['zkontrolovat_isds_'.$z->dmID])):
                            $mess = $cache['zkontrolovat_isds_'.$z->dmID];
                        else:
                            $mess = $isds->prectiZpravu($z->dmID);
                        endif;

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
                        /*foreach( $mess->dmDm->dmFiles->dmFile[0] as $k => $m ) {
                            
                            if ( $k == 'dmEncodedContent' ) continue;
                            if ( is_object($m) ) {
                                echo $k ." = objekt\n";
                            } else {
                                echo $k ." = ". $m ."\n";    
                            }

                            
                        }*/



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

                        $popis  = '';
                        $popis .= "ID datové zprávy    : ". $mess->dmDm->dmID ."\n";// = 342682
                        $popis .= "Věc, předmět zprávy : ". $mess->dmDm->dmAnnotation ."\n";//  = Vaše datová zpráva byla přijata
                        $popis .= "\n";
                        $popis .= "Číslo jednací odeslatele   : ". $mess->dmDm->dmSenderRefNumber ."\n";//  = AB-44656
                        $popis .= "Spisová značka odesílatele : ". $mess->dmDm->dmSenderIdent ."\n";//  = ZN-161
                        $popis .= "Číslo jednací příjemce     : ". $mess->dmDm->dmRecipientRefNumber ."\n";//  = KAV-34/06-ŘKAV/2010
                        $popis .= "Spisová značka příjemce    : ". $mess->dmDm->dmRecipientIdent ."\n";//  = 0.06.00
                        $popis .= "\n";
                        $popis .= "Do vlastních rukou? : ". (!empty($mess->dmDm->dmPersonalDelivery)?"ano":"ne") ."\n";//  =
                        $popis .= "Doručeno fikcí?     : ". (!empty($mess->dmDm->dmAllowSubstDelivery)?"ano":"ne") ."\n";//  =
                        $popis .= "Zpráva určena pro   : ". $mess->dmDm->dmToHands ."\n";//  =
                        $popis .= "\n";
                        $popis .= "Odesílatel:\n";
                        $popis .= "            ". $mess->dmDm->dbIDSender ."\n";//  = hjyaavk
                        $popis .= "            ". $mess->dmDm->dmSender ."\n";//  = Město Milotice
                        $popis .= "            ". $mess->dmDm->dmSenderAddress ."\n";//  = Kovářská 14/1, 37612 Milotice, CZ
                        $popis .= "            ". $mess->dmDm->dmSenderType ." - ". ISDS_Spisovka::typDS($mess->dmDm->dmSenderType) ."\n";//  = 10
                        $popis .= "            org.jednotka: ". $mess->dmDm->dmSenderOrgUnit ." [". $mess->dmDm->dmSenderOrgUnitNum ."]\n";//  =
                        $popis .= "\n";
                        $popis .= "Příjemce:\n";
                        $popis .= "            ". $mess->dmDm->dbIDRecipient ."\n";//  = pksakua
                        $popis .= "            ". $mess->dmDm->dmRecipient ."\n";//  = Společnost pro výzkum a podporu OpenSource
                        $popis .= "            ". $mess->dmDm->dmRecipientAddress ."\n";//  = 40501 Děčín, CZ
                        //$popis .= "Je příjemce ne-OVM povýšený na OVM: ". $mess->dmDm->dmAmbiguousRecipient ."\n";//  =
                        $popis .= "            org.jednotka: ". $mess->dmDm->dmRecipientOrgUnit ." [". $mess->dmDm->dmRecipientOrgUnitNum ."]\n";//  =
                        $popis .= "\n";
                        $popis .= "Status: ". $mess->dmMessageStatus ." - ". ISDS_Spisovka::stavZpravy($mess->dmMessageStatus) ."\n";
                        $dt_dodani = strtotime($mess->dmDeliveryTime);
                        $dt_doruceni = strtotime($mess->dmAcceptanceTime);
                        $popis .= "Datum a čas dodání   : ". date("j.n.Y G:i:s",$dt_dodani) ." (". $mess->dmDeliveryTime .")\n";//  =
                        $popis .= "Datum a čas doručení : ". date("j.n.Y G:i:s",$dt_doruceni) ." (". $mess->dmAcceptanceTime .")\n";//  =
                        $popis .= "Přiblížná velikost všech příloh : ". $mess->dmAttachmentSize ."kB\n";//  =


                        //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitleLaw ."\n";//  =
                        //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitleYear ."\n";//  =
                        //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitleSect ."\n";//  =
                        //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitlePar ."\n";//  =
                        //$popis .= "ID datové zprávy: ". $mess->dmDm->dmLegalTitlePoint ."\n";//  =

                        $zprava = array();
                        $zprava['poradi'] = $this->Epodatelna->getMax();
                        $zprava['rok'] = date('Y');
                        $zprava['isds_signature'] = $z->dmID;
                        $zprava['predmet'] = $z->dmAnnotation;
                        $zprava['popis'] = $popis;
                        $zprava['odesilatel'] = $z->dmSender .', '. $z->dmSenderAddress;
                        //$zprava['odesilatel_id'] = $z->dmAnnotation;
                        $zprava['adresat'] = $config['ucet'] .' ['. $config['idbox'] .']';
                        $zprava['prijato_dne'] = new DateTime();
                        $zprava['doruceno_dne'] = new DateTime($z->dmAcceptanceTime);
                        $zprava['prijal_kdo'] = $user->id;
                        $zprava['prijal_info'] = serialize($user->identity);

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
                        if ( isset($mess->dmDm->dmFiles->dmFile) ) {
                            foreach( $mess->dmDm->dmFiles->dmFile as $index => $file ) {
                                $prilohy[] = array(
                                    'name'=>$file->dmFileDescr,
                                    'size'=>strlen($file->dmEncodedContent),
                                    'mimetype'=> $file->dmMimeType,
                                    'id'=>$index
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

                        if ( $epod_id = $this->Epodatelna->insert($zprava) ) {

                            /* Ulozeni podepsane ISDS zpravy */
                            $data = array(
                                'filename'=>'ep_isds_'.$epod_id .'.zfo',
                                'dir'=>'EP-I-'. sprintf('%06d',$zprava['poradi']).'-'.$zprava['rok'],
                                'typ'=>'5',
                                'popis'=>'Podepsaný originál ISDS zprávy z epodatelny '.$zprava['poradi'].'-'.$zprava['rok']
                                //'popis'=>'Emailová zpráva'
                            );

                            $signedmess = $isds->SignedMessageDownload($z->dmID);

                            if ( $file_o = $UploadFile->uploadEpodatelna($signedmess, $data) ) {
                                // ok
                            } else {
                                $zprava['stav_info'] = 'Originál zprávy se nepodařilo uložit';
                                // false
                            }

                            /* Ulozeni reprezentace zpravy */
                            $data = array(
                                'filename'=>'ep_isds_'.$epod_id .'.bsr',
                                'dir'=>'EP-I-'. sprintf('%06d',$zprava['poradi']).'-'.$zprava['rok'],
                                'typ'=>'5',
                                'popis'=>'Byte-stream reprezentace ISDS zprávy z epodatelny '.$zprava['poradi'].'-'.$zprava['rok']
                                //'popis'=>'Emailová zpráva'
                            );

                            if ( $file = $UploadFile->uploadEpodatelna(serialize($mess), $data) ) {
                                // ok
                                $zprava['stav_info'] = 'Zpráva byla uložena';
                                $zprava['source_id'] = $file->id ."-". $file_o->id;
                                $this->Epodatelna->update(
                                        array('stav'=>1,
                                              'stav_info'=>$zprava['stav_info'],
                                              'source_id'=>$file->id ."-". $file_o->id
                                            ),
                                        array(array('id=%i',$epod_id))
                                );
                            } else {
                                $zprava['stav_info'] = 'Reprezentace zprávy se nepodařilo uložit';
                                // false
                            }

                        } else {
                            $zprava['stav_info'] = 'Zprávu se nepodařilo uložit';
                        }

                        $tmp[] = $zprava;
                        unset($zprava);
                        //break;
                    }
                    
                }

                return ( count($tmp)>0 )?$tmp:null;
            } else {
                return null;
            }
        } else {
            $this->flashMessage('Nepodařilo se připojit k ISDS schránce "'. $config['ucet'] .'"!
                                 <br />
                                 ISDS chyba: '. $isds->StatusCode .' - '. $isds->StatusMessage .'','warning');
            return null;
        }
    }

    public function zkontrolujEmail($config)
    {
        if ( is_null($this->Epodatelna) ) $this->Epodatelna = new Epodatelna();

        $imap = new ImapClient();
        $email_mailbox = '{'. $config['server'] .':'. $config['port'] .''. $config['typ'] .'}'. $config['inbox'];

        $imap_connect = $imap->connect($email_mailbox,$config['login'],$config['password']);
        if ($imap_connect) {
            if ( $imap->count_messages() ) {
                $tmp = array();
                $user = Environment::getUser()->getIdentity();

                $storage_conf = Environment::getConfig('storage');
                eval("\$UploadFile = new ".$storage_conf->type."();");

                $zpravy = $imap->get_head_messages();
                foreach($zpravy as $z) {
                    // kontrola existence v epodatelny
                    if ( ! $this->Epodatelna->existuje($z->message_id ,'email') ) {
                        // nova zprava, ktera neni nahrana v epodatelne

                        // Nacteni kompletni zpravy
                        $mess = $imap->get_message($z->id_part);
                        $popis = '';
                        foreach ($mess->texts as $zpr) {
                            if($zpr->subtype == "HTML") {
                                $zpr->text_convert = str_ireplace("<br>", "\n", $zpr->text_convert);
                                $zpr->text_convert = str_ireplace("<br />", "\n", $zpr->text_convert);
                                $popis .= htmlspecialchars($zpr->text_convert);
                            } else {
                                $popis .= htmlspecialchars($zpr->text_convert);
                            }
                            $popis .= "\n\n";
                        }

                        

                        $zprava = array();
                        $zprava['poradi'] = $this->Epodatelna->getMax();
                        $zprava['rok'] = date('Y');
                        $zprava['email_signature'] = $z->message_id;
                        $zprava['predmet'] = $z->subject;
                        $zprava['popis'] = $popis;
                        $zprava['odesilatel'] = $z->from_address;
                        //$zprava['odesilatel_id'] = $z->dmAnnotation;
                        $zprava['adresat'] = $config['ucet'] .' ['. $config['login'] .']';
                        $zprava['prijato_dne'] = new DateTime();
                        $zprava['doruceno_dne'] = new DateTime( date('Y-m-d H:i:s',$z->udate) );
                        $zprava['prijal_kdo'] = $user->id;
                        $zprava['prijal_info'] = serialize($user->identity);

                        $zprava['sha1_hash'] = sha1($mess->source);

                        $prilohy = array();
                        if( isset($mess->attachments) ) {
                            foreach ($mess->attachments as $pr) {
                                $prilohy[] = array(
                                    'name'=>$pr->filename,
                                    'size'=>$pr->size,
                                    'mimetype'=> FileModel::mimeType($pr->filename),
                                    'id'=>$pr->id_part
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
                        $zprava['source_id'] = null;

                        if ( $epod_id = $this->Epodatelna->insert($zprava) ) {

                            $data = array(
                                'filename'=>'ep_email_'.$epod_id .'.eml',
                                'dir'=>'EP-I-'. sprintf('%06d',$zprava['poradi']).'-'.$zprava['rok'],
                                'typ'=>'5',
                                'popis'=>'Emailová zpráva z epodatelny '.$zprava['poradi'].'-'.$zprava['rok']
                                //'popis'=>'Emailová zpráva'
                            );

                            if ( $file = $UploadFile->uploadEpodatelna($mess->source, $data) ) {
                                // ok
                                $zprava['stav_info'] = 'Zpráva byla uložena';
                                $zprava['source_id'] = $file->id;
                                $this->Epodatelna->update(
                                        array('stav'=>1,
                                              'stav_info'=>$zprava['stav_info'],
                                              'source_id'=>$file->id
                                            ),
                                        array(array('id=%i',$epod_id))
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

                return ( count($tmp)>0 )?$tmp:null;
            } else {
                $this->flashMessage('V emailové schránce "'. $config['ucet'] .'" nejsou žádné zprávy k přijetí.');
                return null;
            }
        } else {
            $this->flashMessage('Nepodařilo se připojit k emailové schránce "'. $config['ucet'] .'"!
                                 <br />
                                 IMAP chyba: '. $imap->error() .'','warning');
            return null;
        }
    }

    public function nactiISDS($source_id)
    {
        $storage_conf = Environment::getConfig('storage');
        eval("\$DownloadFile = new ".$storage_conf->type."();");

        $FileModel = new FileModel();
        $file = $FileModel->getInfo($source_id);
        $res = $DownloadFile->download($file,1);
        return $res;

    }

    public function nactiEmail($source_id, $output = 0)
    {

        $storage_conf = Environment::getConfig('storage');
        eval("\$DownloadFile = new ".$storage_conf->type."();");

        $FileModel = new FileModel();
        $file = $FileModel->getInfo($source_id);
        $res = $DownloadFile->download($file,2);

        if ( $output == 1 ) {
            return $res;
        }

        $tmp = array();
        // Kontrola epodpisu
        $esign = new esignature();
        $esign->setCACert(LIBS_DIR .'/email/ca_certifikaty');
        if ( $esigned = $esign->verifySignature($res, $esign_cert, $esign_status) ) {
            $tmp['signature']['cert'] = $esign_cert;
            $tmp['signature']['cert_info'] = $esign->getInfo($esign_cert);
            $tmp['signature']['status'] = $esign_status;
            $tmp['signature']['signed'] = $esigned;
        } else {
            $tmp['signature']['cert'] = null;
            $tmp['signature']['cert_info'] = null;
            $tmp['signature']['status'] = $esign_status;
            $tmp['signature']['signed'] = $esigned;
        }


        //$imap = new ImapClient();
        $imap = new ImapClientFile();

        if ( $imap->open($res) ) {
            $zprava = $imap->get_head_message(0);
            $tmp['zprava'] = $zprava;
        } else {
            $tmp['zprava'] = null;
        }

        return $tmp;
    }


    public function actionIsds()
    {

        $isds = new ISDS_Spisovka();

        if ( $ISDSBox = $isds->pripojit() ) {

            //$zpravy = $isds->seznamPrichozichZprav();
            //$zpravy = $isds->seznamOdeslanychZprav();
            //$zpravy = $isds->seznamOdeslanychZprav( time()-3600 , time() );

            //echo "<pre>";
            //print_r($zpravy);
            //echo "</pre>";



        } else {
            $this->flashMessage('Nepodařilo se připojit k ISDS schránce "'. $config['ucet'] .'"!
                                 <br />
                                 ISDS chyba: '. $isds->StatusCode .' - '. $isds->StatusMessage .'','warning');
        }

        $this->terminate();
        exit;
    }



}