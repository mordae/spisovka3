<?php

//netteloader=Epodatelna_EvidencePresenter

class Epodatelna_EvidencePresenter extends BasePresenter
{

    private $filtr;
    private $hledat;
    private $Epodatelna;
    private $typ_evidence = null;
    protected $storage;

    public function startup()
    {
        $client_config = Nette\Environment::getVariable('client_config');
        $this->typ_evidence = $client_config->cislo_jednaci->typ_evidence;

        parent::startup();
    }

    public function renderPridelitcj()
    {
        $dokument_id = $this->getParameter('id', null);

        $Dokument = new Dokument();

        $CJ = new CisloJednaci();
        $cjednaci = $CJ->generuj(1);

        $data = array();
        $data['cislojednaci_id'] = $cjednaci->id;
        $data['cislo_jednaci'] = $cjednaci->cislo_jednaci;
        $data['podaci_denik'] = $cjednaci->podaci_denik;
        $data['podaci_denik_poradi'] = $cjednaci->poradove_cislo;
        $data['podaci_denik_rok'] = $cjednaci->rok;

        $Dokument->update($data, [['id=%i', $dokument_id]]);
        $this->flashMessage('číslo jednací přiděleno');
        $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
    }

    public function renderNovy($id)
    {
        /* Nacteni zpravy */
        if (is_null($this->Epodatelna))
            $this->Epodatelna = new Epodatelna();

        $epodatelna_id = $id;
        $zprava = $this->Epodatelna->getInfo($epodatelna_id);

        $this->template->Typ_evidence = $this->typ_evidence;

        if ($zprava) {

            $this->template->Zprava = $zprava;
            $subjekt = new stdClass();
            $prilohy = unserialize($zprava->prilohy);
            if ($prilohy) {
                $this->template->Prilohy = $prilohy;
            } else {
                $this->template->Prilohy = null;
            }

            $original = null;
            if (!empty($zprava->email_id)) {
                // Nacteni originalu emailu
                if (!empty($zprava->file_id)) {
                    $original = Epodatelna_DefaultPresenter::nactiEmail($this->storage,
                                    $zprava->file_id);
                }

                $subjekt->nazev_subjektu = isset($original['zprava']->from->personal) ? $original['zprava']->from->personal
                            : $zprava->odesilatel;
                $subjekt->prijmeni = @$original['zprava']->from->personal;
                $subjekt->email = @$original['zprava']->from->email;
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
                    $subjekt->adresa_ulice = $original['signature']['cert_info']['adresa'];
                }

                $SubjektModel = new Subjekt();
                $subjekt_databaze = $SubjektModel->hledat($subjekt, 'email');
                $this->template->Subjekt = array('original' => $subjekt, 'databaze' => $subjekt_databaze);
            } else if (!empty($zprava->isds_id)) {
                // Nacteni originalu DS
                if (!empty($zprava->file_id)) {
                    $file_id = explode("-", $zprava->file_id);
                    $original = Epodatelna_DefaultPresenter::nactiISDS($this->storage,
                                    $file_id[0]);
                    $original = unserialize($original);

                    // odebrat obsah priloh, aby to neotravovalo
                    unset($original->dmDm->dmFiles);

                    //echo "<pre>"; print_r($original); exit;
                }

                $subjekt->id_isds = $original->dmDm->dbIDSender;
                $subjekt->nazev_subjektu = $original->dmDm->dmSender;
                $subjekt->type = ISDS_Spisovka::typDS($original->dmDm->dmSenderType);
                if (isset($original->dmDm->dmSenderAddress)) {
                    $res = ISDS_Spisovka::parseAddress($original->dmDm->dmSenderAddress);
                    foreach ($res as $key => $value)
                        $subjekt->$key = $value;
                }

                $SubjektModel = new Subjekt();
                $subjekt_databaze = $SubjektModel->hledat($subjekt, 'isds');
                $this->template->Subjekt = array('original' => $subjekt, 'databaze' => $subjekt_databaze);
            } else {
                // zrejme odchozi zprava ven
            }
            $this->template->Original = $original;

            $this->template->Identifikator = $this->Epodatelna->identifikator($zprava,
                    $original);
        } else {
            $this->flashMessage('Požadovaná zpráva neexistuje!', 'warning');
            $this->redirect(':Epodatelna:Default:nove');
        }



        /* Priprava dokumentu */
        $Dokumenty = new Dokument();

        $rozdelany = Nette\Environment::getSession('s3_rozdelany');
        $rozdelany_dokument = null;

        if (isset($rozdelany->is)) {
            $args_rozd = array();
            $args_rozd['where'] = array(
                array('id=%i', $rozdelany->dokument_id)
            );
            $rozdelany_dokument = $Dokumenty->seznamKlasicky($args_rozd);
        }

        if (count($rozdelany_dokument) > 0) {
            $dokument = $rozdelany_dokument[0];

            $DokumentSpis = new DokumentSpis();
            $DokumentSubjekt = new DokumentSubjekt();

            $spisy = $DokumentSpis->spisy($dokument->id);
            $this->template->Spisy = $spisy;

            $subjekty = $DokumentSubjekt->subjekty($dokument->id);
            $this->template->Subjekty = $subjekty;

            if ($this->typ_evidence == 'priorace') {
                // Nacteni souvisejicicho dokumentu
                $Souvisejici = new SouvisejiciDokument();
                $this->template->SouvisejiciDokumenty = $Souvisejici->souvisejici($dokument->id);
            }
        } else {
            $pred_priprava = array(
                "nazev" => '',
                "popis" => "",
                "stav" => 0,
                "dokument_typ_id" => "1",
                "cislo_jednaci_odesilatele" => "",
                "datum_vzniku" => $zprava->doruceno_dne,
                "lhuta" => "30",
                "poznamka" => "",
            );
            $dokument = $Dokumenty->ulozit($pred_priprava);

            $rozdelany->is = 1;
            $rozdelany->dokument_id = $dokument->id;

            $this->template->Spisy = null;
            $this->template->Subjekty = null;
            //$this->template->Prilohy = null;
            $this->template->SouvisejiciDokumenty = null;
            $this->template->Typ_evidence = $this->typ_evidence;
        }

        $CJ = new CisloJednaci();
        $this->template->cjednaci = $CJ->generuj();


        if ($dokument) {
            $this->template->Dok = $dokument;
        } else {
            $this->template->Dok = null;
            $this->flashMessage('Dokument není připraven k vytvoření', 'warning');
        }

        new SeznamStatu($this, 'seznamstatu');
    }

    public function renderOdmitnout($id)
    {
        /* Nacteni zpravy */
        $Epodatelna = new Epodatelna();

        $epodatelna_id = $this->getParameter('id', null);
        $zprava = $Epodatelna->getInfo($epodatelna_id);

        if (!empty($zprava->isds_id)) {
            if (!empty($zprava->file_id)) {
                $file_id = explode("-", $zprava->file_id);
                $original = Epodatelna_DefaultPresenter::nactiISDS($this->storage, $file_id[0]);
                $original = unserialize($original);
                // odebrat obsah priloh, aby to neotravovalo
                unset($original->dmDm->dmFiles);
                $this->template->original = $original->dmDm;
            }
        } else {
            $this->template->original = null;
        }

        $this->template->Zprava = $zprava;
    }

    public function actionHromadna()
    {
        $data = $this->getHttpRequest()->getPost();

        $seznam = array();
        if (isset($data['id'])) {
            // pouze jedno
            $seznam[$data['id']] = (isset($data['volba_evidence'][$data['id']])) ? $data['volba_evidence'][$data['id']]
                        : 0;
        } else if (isset($data['volba_evidence'])) {
            // vsechny
            $seznam = $data['volba_evidence'];
        }

        if (count($seznam) > 0) {
            foreach ($seznam as $id => $typ_evidence) {
                switch ($typ_evidence) {
                    case 1: // evidovat

                        $evidence = array(
                            'epodatelna_id' => $id,
                            'nazev' => $data['vec'][$id],
                            /* 'cislo_jednaci_odesilatele' => $data['cjednaci_odesilatele'][$id], */
                            'popis' => $data['popis'][$id],
                            'poznamka' => $data['poznamka'][$id],
                            'predano_poznamka' => $data['predat_poznamka'][$id],
                            'predano_user' => null,
                            'predano_org' => null,
                            'subjekt' => isset($data['subjekt'][$id]) ? $data['subjekt'][$id] : null,
                        );

                        if (isset($data['predat'][$id])) {
                            $predat_typ = substr($data['predat'][$id], 0, 1);
                            if ($predat_typ == "u") {
                                $evidence['predano_user'] = (int) substr($data['predat'][$id],
                                                1);
                            } else if ($predat_typ == "o") {
                                $evidence['predano_org'] = (int) substr($data['predat'][$id], 1);
                            }
                        }

                        // try {
                        $cislo = $this->vytvorit($evidence);
                        echo '<div class="evidence_report">Zpráva byla zaevidována ve spisové službě pod číslem "<a href="' . $this->link(':Spisovka:Dokumenty:detail',
                                array("id" => $cislo['id'])) . '" target="_blank">' . $cislo['jid'] . '</a>".</div>';
                        /* } catch (Exception $e) {
                          echo '###Zprávu číslo '.$id.' se nepodařilo zaevidovat do spisové služby.';
                          echo ' CHYBA: '. $e->getMessage();
                          } */

                        break;
                    case 2: // evidovat v jinem evidenci
                        $evidence = array(
                            'id' => $id,
                            'evidence' => $data['evidence'][$id]
                        );
                        try {
                            $this->zaevidovat($evidence);
                            echo '<div class="evidence_report">Zpráva byla zaevidována v evidenci "' . $data['evidence'][$id] . '".</div>';
                        } catch (Exception $e) {
                            echo '###Zprávu číslo ' . $id . ' se nepodařilo zaevidovat do jiné evidence.';
                            echo ' CHYBA: ' . $e->getMessage();
                        }
                        break;
                    case 3: // odmitnout
                        $typ = $data['odmitnout_typ'][$id];
                        try {
                            if ($typ == 1) {
                                $evidence = array(
                                    'id' => $id,
                                    'stav_info' => $data['duvod_odmitnuti'][$id],
                                    'upozornit' => (isset($data['odmitnout'][$id]) ? 1 : 0),
                                    'email' => $data['zprava_email'][$id],
                                    'predmet' => $data['zprava_predmet'][$id],
                                    'zprava' => $data['zprava_odmitnuti'][$id],
                                );
                                $this->odmitnoutEmail($evidence, true);
                            } else if ($typ == 2) {
                                $evidence = array(
                                    'id' => $id,
                                    'stav_info' => $data['duvod_odmitnuti'][$id]
                                );
                                $this->odmitnoutISDS($evidence);
                            }
                            echo '<div class="evidence_report">Zpráva byla odmítnuta.</div>';
                        } catch (Exception $e) {
                            echo '###Zprávu číslo ' . $id . ' se nepodařilo odmítnout.';
                            echo ' CHYBA: ' . $e->getMessage();
                        }
                        break;
                    default:
                        break;
                }
            }
        }

        exit;
    }

    protected function createComponentNovyForm()
    {
        if (isset($this->template->Dok)) {
            $dokument_id = isset($this->template->Dok->id) ? $this->template->Dok->id : 0;
        } else {
            $dokument_id = 0;
        }

        if (isset($this->template->Zprava)) {
            $zprava = isset($this->template->Zprava) ? $this->template->Zprava : null;
        } else {
            $zprava = null;
        }
        if (isset($this->template->Original)) {
            $original = isset($this->template->Original) ? $this->template->Original : null;
        } else {
            $original = null;
        }

        $typy_dokumentu = TypDokumentu::dostupneUzivateli();

        $form = new Spisovka\Form();
        $form->addHidden('dokument_id')
                ->setValue($dokument_id);
        $form->addHidden('epodatelna_id')
                ->setValue(@$zprava->id);
        $form->addHidden('predano_user');
        $form->addHidden('predano_org');
        $form->addHidden('predano_poznamka');

        if ($this->typ_evidence == 'sberny_arch') {
            $form->addText('poradi', 'Pořadí dokumentu ve sberném archu:', 4, 4)
                            ->setValue(1)
                    ->controlPrototype->readonly = TRUE;
        }


        $form->addText('nazev', 'Věc:', 80, 100)
                ->setValue(@$zprava->predmet);
        $form->addTextArea('popis', 'Popis:', 80, 3);

        $form->addSelect('dokument_typ_id', 'Typ dokumentu:', $typy_dokumentu);
        if (isset($typy_dokumentu[1]))
            $form['dokument_typ_id']->setDefaultValue(1);

        /* if ( !empty($zprava->email_id) ) {
          foreach ($typ_dokumentu_extra as $tde) {
          if ( $tde->typ == 1 && $tde->smer == 0 ) {
          $id_tde = $tde->id;
          break;
          }
          }
          $form->addSelect('dokument_typ_id', 'Typ Dokumentu:', $typ_dokumentu)
          ->setValue($id_tde);
          } else if ( !empty($zprava->isds_id) ) {
          foreach ($typ_dokumentu_extra as $tde) {
          if ( $tde->typ == 2 && $tde->smer == 0 ) {
          $id_tde = $tde->id;
          break;
          }
          }
          $form->addSelect('typ_dokumentu_id', 'Typ Dokumentu:', $typ_dokumentu)
          ->setValue($id_tde);
          } else {
          $form->addSelect('typ_dokumentu_id', 'Typ Dokumentu:', $typ_dokumentu)
          ->setValue(1);
          } */

        $form->addText('cislo_jednaci_odesilatele', 'Číslo jednací odesilatele:', 50, 50)
                ->setValue(@$original->zprava['cislo_jednaci_odesilatele']);

        $unixtime = strtotime(@$zprava->doruceno_dne);
        $datum = date('d.m.Y', $unixtime);
        $cas = date('H:i:s', $unixtime);
        $form->addDatePicker('datum_vzniku', 'Datum doručení:')
                ->setValue($datum);
        $form->addText('datum_vzniku_cas', 'Čas doručení:', 10, 15)
                ->setValue($cas);

        $form->addText('lhuta', 'Lhůta k vyřízení:', 5, 15)
                ->setValue('30')
                ->setOption('description', 'dní')
                ->addRule(Nette\Forms\Form::NUMERIC,
                'Lhůta k vyřízení musí být číslo');

        $form->addTextArea('poznamka', 'Poznámka:', 80, 6);
        if (!empty($zprava->email_id) && Settings::get('epodatelna_copy_email_into_documents_note'))
            $form['poznamka']->setValue(@html_entity_decode($zprava->popis));

        $form->addTextArea('predani_poznamka', 'Poznámka:', 80, 3);

        $form->addText('pocet_listu', 'Počet listů:', 5, 10)->addCondition(Nette\Forms\Form::FILLED)->addRule(Nette\Forms\Form::NUMERIC,
                'Počet listů musí být číslo.');
        $form->addText('pocet_priloh', 'Počet příloh:', 5, 10)->addCondition(Nette\Forms\Form::FILLED)->addRule(Nette\Forms\Form::NUMERIC,
                'Počet příloh musí být číslo.');


        $form->addSubmit('novy', 'Vytvořit')
                ->onClick[] = array($this, 'vytvoritClicked');
        $form->addSubmit('storno', 'Zrušit')
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'stornoSeznamClicked');

        return $form;
    }

    public function vytvoritClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $Dokument = new Dokument();

        $dokument_id = $data['dokument_id'];
        $epodatelna_id = $data['epodatelna_id'];
        $data['stav'] = 1;

        // uprava casu
        $data['datum_vzniku'] = $data['datum_vzniku'] . " " . $data['datum_vzniku_cas'];
        unset($data['datum_vzniku_cas']);

        if (is_null($this->Epodatelna))
            $this->Epodatelna = new Epodatelna();
        $zprava = $this->Epodatelna->getInfo($epodatelna_id);

        $post_data = $this->getHttpRequest()->post;
        $subjekty = isset($post_data['subjekt']) ? $post_data['subjekt'] : null;

        // predani
        $predani_poznamka = $data['predani_poznamka'];

        unset($data['predani_poznamka'], $data['dokument_id'], $data['epodatelna_id']);

        try {
            $data['poradi'] = 1;

            // 1-email, 2-isds
            $data['zpusob_doruceni_id'] = $zprava->email_id ? 1 : 2;

            $predani = ['predano_user' => $data->predano_user, 'predano_org' => $data->predano_org,
                'predano_poznamka' => $data->predano_poznamka];
            unset($data->predano_user, $data->predano_org, $data->predano_poznamka);

            $dokument = $Dokument->ulozit($data, $dokument_id);

            if ($dokument) {

                // Ulozeni prilohy
                if ($zprava->email_id) {
                    $this->emailPrilohy($epodatelna_id, $dokument_id);
                } else if ($zprava->isds_id) {
                    $this->isdsPrilohy($epodatelna_id, $dokument_id);
                }

                // Ulozeni adresy
                if ($subjekty) {
                    $DokumentSubjekt = new DokumentSubjekt();
                    foreach ($subjekty as $subjekt_id => $subjekt_status)
                        if ($subjekt_status == 'on')
                            $DokumentSubjekt->pripojit($dokument_id, $subjekt_id, 'O');
                }

                // Pridani informaci do epodatelny
                if (is_null($this->Epodatelna))
                    $this->Epodatelna = new Epodatelna();
                $info = array(
                    'dokument_id' => $dokument_id,
                    'evidence' => 'spisovka',
                    'stav' => '10',
                    'stav_info' => 'Zpráva přidána do spisové služby jako ' . $dokument->jid
                );
                $this->Epodatelna->update($info, array(array('id=%i', $epodatelna_id)));

                $Workflow = new Workflow();
                $Workflow->vytvorit($dokument_id, $predani_poznamka);

                $Log = new LogModel();
                $Log->logDokument($dokument_id, LogModel::DOK_NOVY);

                $this->flashMessage('Dokument byl vytvořen.');

                $rozdelany = Nette\Environment::getSession('s3_rozdelany');
                unset($rozdelany->is, $rozdelany->dokument_id, $rozdelany);

                if (!empty($predani['predano_user']) || !empty($predani['predano_org'])) {
                    /* Dokument predan */
                    $Workflow->predat($dokument_id, $predani['predano_user'],
                            $predani['predano_org'], $predani['predano_poznamka']);
                    $this->flashMessage('Dokument předán zaměstnanci nebo organizační jednotce.');

                    if (!empty($predani['predano_user'])) {
                        $user_info = UserModel::getIdentity($predani['predano_user']);
                        $predano = Osoba::displayName($user_info);
                    } else if (!empty($predani['predano_org'])) {
                        $Org = new Orgjednotka();
                        $orgjednotka = $Org->getInfo($predani['predano_org']);
                        $predano = @$orgjednotka->ciselna_rada . " - " . @$orgjednotka->plny_nazev;
                    }
                } else {
                    $predano = Osoba::displayName($this->user->getIdentity()->identity);
                }

                if (!empty($zprava->email_id)) {
                    $email_info = array(
                        'jid' => $dokument->jid,
                        'nazev' => $zprava->predmet,
                        'predano' => $predano
                    );
                    EmailAvizo::epodatelna_zaevidovana($zprava->odesilatel, $email_info);
                }

                $this->redirect(':Spisovka:Dokumenty:detail', array('id' => $dokument_id));
            } else {
                $this->flashMessage('Dokument se nepodařilo vytvořit.', 'warning');
            }
        } catch (DibiException $e) {
            $this->flashMessage('Dokument se nepodařilo vytvořit.', 'warning');
            $this->flashMessage('CHYBA: ' . $e->getMessage(), 'warning');
        }
    }

    /**
     * Zaeviduje zprávu po odeslání hromadného formuláře.
     * Aplikační logika je duplikovaná v metodě vytvoritClicked()
     * @param array $data
     * @return array informace o čísle, pod kterým byl vytvořen dokument v modulu spisovka
     * @throws Exception
     */
    public function vytvorit($data)
    {
        if (is_null($this->Epodatelna))
            $this->Epodatelna = new Epodatelna();
        $epodatelna_id = $data['epodatelna_id'];
        $zprava = $this->Epodatelna->getInfo($epodatelna_id);

        if (!$zprava)
            throw new Exception('Nelze získat informace o zprávě!');

        $Dokument = new Dokument();

        $dokument_data = array(
            "nazev" => $data['nazev'],
            "popis" => $data['popis'],
            "stav" => 1,
            "dokument_typ_id" => "1",
            /* "cislo_jednaci_odesilatele" => $data['cislo_jednaci_odesilatele'], */
            "datum_vzniku" => $zprava['doruceno_dne'],
            "lhuta" => "30",
            "poznamka" => $data['poznamka'],
            'zpusob_doruceni_id' => $zprava->email_id ? 1 : 2
        );
        $dokument = $Dokument->ulozit($dokument_data);

        if (!$dokument)
            throw new Exception('Dokument se nepodařilo vytvořit!');

        $dokument_id = $dokument->id;

        // Ulozeni prilohy
        if (!empty($zprava->email_id)) {
            $this->emailPrilohy($epodatelna_id, $dokument_id);
        } else if (!empty($zprava->isds_id)) {
            $this->isdsPrilohy($epodatelna_id, $dokument_id);
        }

        // Ulozeni adresy
        if ($data['subjekt']) {
            $DokumentSubjekt = new DokumentSubjekt();
            foreach ($data['subjekt'] as $subjekt_id => $subjekt_status) {
                if ($subjekt_status == 'on') {
                    $DokumentSubjekt->pripojit($dokument_id, $subjekt_id, 'O');
                }
            }
        }

        // Pridani informaci do epodatelny
        $epod_info = array(
            'dokument_id' => $dokument_id,
            'evidence' => 'spisovka',
            'stav' => '10',
            'stav_info' => 'Zpráva přidána do spisové služby jako ' . $dokument->jid
        );
        $this->Epodatelna->update($epod_info, array(array('id=%i', $epodatelna_id)));

        $Workflow = new Workflow();
        $Workflow->vytvorit($dokument_id, '');

        $Log = new LogModel();
        $Log->logDokument($dokument_id, LogModel::DOK_NOVY);

        if (!empty($data['predano_user']) || !empty($data['predano_org'])) {
            /* Dokument predan */
            $Workflow->predat($dokument_id, $data['predano_user'], $data['predano_org'],
                    $data['predano_poznamka']);
            $this->flashMessage('Dokument předán zaměstnanci nebo organizační jednotce.');

            if (!empty($data['predano_user'])) {
                $user_info = UserModel::getIdentity($data['predano_user']);
                $predano = Osoba::displayName($user_info);
            } else if (!empty($data['predano_org'])) {
                $Org = new Orgjednotka();
                $orgjednotka = $Org->getInfo($data['predano_org']);
                $predano = @$orgjednotka->ciselna_rada . " - " . @$orgjednotka->plny_nazev;
            }
        } else {
            $predano = Osoba::displayName(@$this->user->getIdentity()->identity);
        }

        if (!empty($zprava->email_id)) {
            $email_info = array(
                'jid' => $dokument->jid,
                'nazev' => $zprava->predmet,
                'predano' => $predano
            );
            EmailAvizo::epodatelna_zaevidovana($zprava->odesilatel, $email_info);
        }

        return array('jid' => $dokument->jid, 'id' => $dokument_id);
    }

    private function emailPrilohy($epodatelna_id, $dokument_id)
    {
        $prilohy = Epodatelna_PrilohyPresenter::emailPrilohy($this->storage, $epodatelna_id);

        $UploadFile = $this->storage;

        $DokumentFile = new DokumentPrilohy();

        // nahrani originalu
        if (is_null($this->Epodatelna))
            $this->Epodatelna = new Epodatelna();
        $info = $this->Epodatelna->getInfo($epodatelna_id);
        $source = explode("-", $info->file_id);
        if (isset($source[0])) {

            $res = Epodatelna_DefaultPresenter::nactiEmail($this->storage, $source[0], 1);
            if ($fp = @fopen($res, 'rb')) {
                $res_data = fread($fp, filesize($res));
                @fclose($fp);
            }

            $data = array(
                'filename' => 'emailova_zprava.eml',
                'dir' => date('Y') . '/DOK-' . sprintf('%06d', $dokument_id) . '-' . date('Y'),
                'typ' => '5',
                'popis' => 'Originální emailová zpráva',
                'charset' => null
                    //'popis'=>'Emailová zpráva'
            );

            if ($filep = $UploadFile->uploadDokumentSource($res_data, $data)) {
                // zapiseme i do
                $DokumentFile->pripojit($dokument_id, $filep->id);
            } else {
                // false
            }
        }


        // nahrani prilohy
        if (count($prilohy) > 0) {
            foreach ($prilohy as $file) {

                // prekopirovani na pozadovane misto
                $data = array(
                    'filename' => $file['file_name'],
                    'nazev' => $file['file_name'],
                    'dir' => date('Y') . '/DOK-' . sprintf('%06d', $dokument_id) . '-' . date('Y'),
                    'typ' => '2',
                    'popis' => '',
                    'charset' => $file['charset'],
                        //'popis'=>'Emailová zpráva'
                );

                if ($fp = fopen($file['file'], 'rb')) {
                    $source = fread($fp, filesize($file['file']));
                    if ($file = $UploadFile->uploadDokumentSource($source, $data)) {
                        // zapiseme i do
                        $DokumentFile->pripojit($dokument_id, $file->id);
                    } else {
                        // false
                    }
                    @fclose($fp);
                } else {
                    // nelze kopirovat
                }
            }
            return true;
        } else {
            return null;
        }
    }

    private function isdsPrilohy($epodatelna_id, $dokument_id)
    {
        $prilohy = Epodatelna_PrilohyPresenter::isdsPrilohy($this->storage, $epodatelna_id);

        $UploadFile = $this->storage;

        $DokumentFile = new DokumentPrilohy();

        // nahrani originalu
        if (is_null($this->Epodatelna))
            $this->Epodatelna = new Epodatelna();
        $info = $this->Epodatelna->getInfo($epodatelna_id);
        if ($info) {

            $FileModel = new FileModel();
            $file_info = $FileModel->select(array(array('real_name=%s', 'ep-isds-' . $epodatelna_id . '.zfo')))->fetch();
            if ($file_info) {
                $res = Epodatelna_DefaultPresenter::nactiISDS($this->storage, $file_info->id);

                $data = array(
                    'filename' => 'datova_zprava_' . $info->isds_id . '.zfo',
                    'dir' => date('Y') . '/DOK-' . sprintf('%06d', $dokument_id) . '-' . date('Y'),
                    'typ' => '5',
                    'popis' => 'Podepsaná originální datová zpráva'
                        //'popis'=>'Emailová zpráva'
                );

                if ($filep = $UploadFile->uploadDokumentSource($res, $data)) {
                    // zapiseme i do
                    $DokumentFile->pripojit($dokument_id, $filep->id);
                } else {
                    // false
                }
            }
        }

        // nahrani priloh
        if (count($prilohy) > 0) {

            foreach ($prilohy as $file) {

                // prekopirovani na pozadovane misto
                $data = array(
                    'filename' => $file['file_name'],
                    'dir' => date('Y') . '/DOK-' . sprintf('%06d', $dokument_id) . '-' . date('Y'),
                    'typ' => '2',
                    'popis' => ''
                        //'popis'=>'Emailová zpráva'
                );

                if ($filep = $UploadFile->uploadDokumentSource($file['file'], $data)) {
                    // zapiseme i do
                    $DokumentFile->pripojit($dokument_id, $filep->id);
                } else {
                    // false
                }
            }
            return true;
        } else {
            return null;
        }
    }

    public function stornoClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();
        $dokument_id = $data['dokument_id'];
        //$dokument_version = $data['dokument_version'];
        $this->redirect('this', array('id' => $dokument_id));
    }

    public function stornoSeznamClicked()
    {
        $this->redirect(':Epodatelna:Default:nove');
    }

    protected function createComponentEvidenceForm()
    {
        $form = new Spisovka\Form();
        $form->addHidden('id')
                ->setValue($this->getParameter('id'));
        $form->addText('evidence', 'Evidence:', 50, 100)
                ->addRule(Nette\Forms\Form::FILLED, 'Název evidence musí být vyplněn!');
        $form->addSubmit('evidovat', 'Zaevidovat')
                ->onClick[] = array($this, 'zaevidovatClicked');
        $form->onError[] = array($this, 'validationFailed');
        
        return $form;
    }

    public function validationFailed()
    {
        $this->redirect('Default:detail', ['id' => $this->getParameter('id')]);
    }
    
    public function zaevidovatClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        try {
            $this->zaevidovat($data);
            $this->flashMessage('Zpráva byla zaevidována v evidenci "' . $data['evidence'] . '".');
        } catch (DibiException $e) {
            $e->getMessage();
            $this->flashMessage('Zprávu se nepodařilo zaevidovat do jiné evidence.', 'warning');
        }
        
        $this->redirect(':Epodatelna:Default:detail', array('id' => $data['id']));
    }

    private function zaevidovat($data)
    {
        if (is_null($this->Epodatelna))
            $this->Epodatelna = new Epodatelna();
        $info = array(
            'evidence' => $data['evidence'],
            'stav' => '11',
            'stav_info' => 'Zpráva zaevidována v evidenci: ' . $data['evidence']
        );
        return $this->Epodatelna->update($info, array(array('id = %i', $data['id'])));
    }

    protected function createComponentOdmitnoutEmailForm()
    {
        $epodatelna_id = $this->getParameter('id', null);
        $zprava = @$this->template->Zprava;

        $mess = "\n\n--------------------\n";
        $mess .= @$zprava->popis;

        $form = new Spisovka\Form();
        $form->addHidden('id')
                ->setValue($epodatelna_id);
        $form->addTextArea('stav_info', 'Důvod odmítnutí:', 80, 6)
                ->addRule(Nette\Forms\Form::FILLED, 'Důvod odmítnutí musí být vyplněn!');

        $form->addCheckbox('upozornit', 'Poslat upozornění odesilateli?')
                ->setValue(true);
        $form->addText('email', 'Komu:', 80, 100)
                ->setValue(@$zprava->odesilatel);
        $form->addText('predmet', 'Předmět:', 80, 100)
                ->setValue('RE: ' . @$zprava->predmet);
        $form->addTextArea('zprava', 'Zpráva pro odesilatele:', 80, 6)
                ->setValue($mess);

        $form->addSubmit('odmitnout', 'Provést')
                ->onClick[] = array($this, 'odmitnoutEmailClicked');
        $form->onError[] = array($this, 'validationFailed');

        return $form;
    }

    public function odmitnoutEmailClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        try {
            $this->odmitnoutEmail($data);
            $this->flashMessage('Zpráva byla odmítnuta.');
        } catch (DibiException $e) {
            $e->getMessage();
            $this->flashMessage('Zprávu se nepodařilo odmítnout.', 'warning');
        }
        
        $this->redirect(':Epodatelna:Default:detail', array('id' => $data['id']));
    }

    private function odmitnoutEmail($data, $hromadna = false)
    {
        if (is_null($this->Epodatelna))
            $this->Epodatelna = new Epodatelna();
        $info = array(
            'stav' => '100',
            'stav_info' => $data['stav_info']
        );
        $this->Epodatelna->update($info, array(array('id=%i', $data['id'])));

        // odeslat email odesilateli?
        if ($data['upozornit'] == true) {

            $ep = (new Spisovka\ConfigEpodatelna())->get();
            if (isset($ep['odeslani'][0])) {
                if ($ep['odeslani'][0]['aktivni'] == '1') {

                    $mail = new ESSMail;
                    $mail->setFromConfig();
                    $mail->signed(1);
                    $mail->addTo($data['email']);
                    $mail->setSubject($data['predmet']);
                    $mail->setBodySign($data['zprava']);
                    $mail->send();

                    if ($hromadna) {
                        echo 'Upozornění odesílateli na adresu "' . htmlentities($data['email']) . '" bylo úspěšně odesláno.';
                    } else {
                        $this->flashMessage('Upozornění odesílateli na adresu "' . htmlentities($data['email']) . '" bylo úspěšně odesláno.');
                    }
                }
            } else {
                if ($hromadna) {
                    echo '###Upozornění odesílateli se nepodařilo odeslat. Nebyl zjištěn adresát pro odesílání emailových zpráv ze spisové služby.';
                } else {
                    $this->flashMessage('Upozornění odesílateli se nepodařilo odeslat. Nebyl zjištěn adresát pro odesílání emailových zpráv ze spisové služby.',
                            'warning');
                }
            }
        }
    }

    protected function createComponentOdmitnoutISDSForm()
    {
        $epodatelna_id = $this->getParameter('id', null);
//        $zprava = @$this->template->Zprava;
//        $original = @$this->template->original;

        $form = new Spisovka\Form();
        $form->addHidden('id')
                ->setValue($epodatelna_id);
        $form->addTextArea('stav_info', 'Důvod odmítnutí:', 80, 6)
                ->addRule(Nette\Forms\Form::FILLED, 'Důvod odmítnutí musí být vyplněn!');

        /* $form->addCheckbox('upozornit', 'Poslat upozornění odesilateli?')
          ->setValue(false);
          $form->addText('isds', 'Komu:', 80, 100)
          ->setValue(@$original->dbIDSender);
          $form->addText('predmet', 'Předmět:', 80, 100)
          ->setValue('[odmítnuto] ' . @$zprava->predmet); */

        $form->addSubmit('odmitnout', 'Provést')
                ->onClick[] = array($this, 'odmitnoutISDSClicked');
        $form->onError[] = array($this, 'validationFailed');

        return $form;
    }

    public function odmitnoutISDSClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        try {
            $this->odmitnoutISDS($data);
            $this->flashMessage('Zpráva byla odmítnuta.');
        } catch (DibiException $e) {
            $e->getMessage();
            $this->flashMessage('Zprávu se nepodařilo odmítnout.', 'warning');
        }
        
        $this->redirect(':Epodatelna:Default:detail', array('id' => $data['id']));
    }

    private function odmitnoutISDS($data)
    {
        if (is_null($this->Epodatelna))
            $this->Epodatelna = new Epodatelna();
        $info = array(
            'stav' => '100',
            'stav_info' => $data['stav_info']
        );
        $this->Epodatelna->update($info, array(array('id=%i', $data['id'])));
    }

    public function renderJiny($id)
    {
        
    }
}
