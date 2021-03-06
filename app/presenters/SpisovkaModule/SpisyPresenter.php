<?php

class SpisyPresenter extends BasePresenter
{

    /**
     *
     * @return \Spisovka\Form
     */
    protected function createForm()
    {
        $spousteci = SpisovyZnak::spousteci_udalost(null, 1);
        $skar_znak = array('' => 'nezadán', 'A' => 'A', 'S' => 'S', 'V' => 'V');

        $m = new Spis();
        $params = ['where' => ["tb.typ = 'F'"]];
        $folders = $m->selectBox(0, $params);
        $folders = [0 => '(hlavní větev)'] + $folders;

        $form = new Spisovka\Form();

        $form->addText('nazev', 'Název spisu:', 50, 80)
                ->addRule(Nette\Forms\Form::FILLED, 'Název spisu musí být vyplněn!');
        $form->addText('popis', 'Popis:', 50, 200);
        $form->addSelect('parent_id', 'Složka:', $folders);

        $form->addComponent(new SpisovyZnakComponent(), 'spisovy_znak_id');
        $form->getComponent('spisovy_znak_id');

        $form->addSelect('skartacni_znak', 'Skartační znak:', $skar_znak);
        $form->addText('skartacni_lhuta', 'Skartační lhůta: ', 5, 5)
                ->addCondition(Spisovka\Form::FILLED)
                ->addRule(Spisovka\Form::INTEGER);
        $form->addSelect('spousteci_udalost_id', 'Spouštěcí událost:', $spousteci);

        return $form;
    }

    protected function createComponentNovyForm()
    {
        $form = $this->createForm();

        $form->addSubmit('vytvorit', 'Vytvořit');
        $form->addSubmit('storno', 'Zrušit')
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'stornoClicked');

        if ($this->isAjax()) {
            $form->getElementPrototype()->onsubmit("return spisVytvoritSubmit(this);");
            $form['storno']->controlPrototype->onclick("return closeDialog();");
            $form->onSuccess[] = array($this, 'vytvoritAjaxClicked');
        } else
            $form->onSuccess[] = array($this, 'vytvoritClicked');

        return $form;
    }

    public function upravitClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();
        $spis_id = $this->getParameter('id');

        try {
            $Spisy = new Spis();
            $Spisy->upravit($data, $spis_id);
            $this->flashMessage('Spis  "' . $data['nazev'] . '"  byl upraven.');
        } catch (Exception $e) {
            $this->flashMessage('Spis "' . $data['nazev'] . '" se nepodařilo upravit.',
                    'warning');
            $this->flashMessage($e->getMessage(), 'warning');
        }

        $this->redirect("detail", array('id' => $spis_id));
    }

    public function vytvoritClicked(Nette\Application\UI\Form $form, $data)
    {
        $Spisy = new Spis();

        try {
            $Spisy->vytvorit($data);
            $this->flashMessage('Spis "' . $data['nazev'] . '"  byl vytvořen.');
        } catch (Exception $e) {
            $this->flashMessage('Spis "' . $data['nazev'] . '" se nepodařilo vytvořit.',
                    'warning');
            $this->flashMessage($e->getMessage(), 'warning');
            return;
        }

        $this->redirect("default");
    }

    public function stornoClicked()
    {
        $id = $this->getParameter('id');
        if ($id !== null) {
            $this->redirect('detail', ['id' => $id]);
        } else {
            $this->redirect('default');
        }
    }

    protected function createComponentUpravitForm()
    {
        $form = $this->createForm();

        $spis = isset($this->template->Spis) ? $this->template->Spis : null;
        if ($spis)
            foreach ($spis as $key => $value) {
                if (isset($form[$key]))
                    $form[$key]->setDefaultValue($value);
            }

        $form->addSubmit('upravit', 'Upravit')
                ->onClick[] = array($this, 'upravitClicked');
        $form->addSubmit('storno', 'Zrušit')
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'stornoClicked');

        return $form;
    }

    /**
     * @param boolean $upravit - formulář upravit složku nebo nová složka
     * @return \Spisovka\Form
     */
    protected function createFolderForm($upravit)
    {
        $form = new Spisovka\Form();
        
        $m = new Spis();
        $params = ['where' => ["tb.typ = 'F'"]];
        if ($upravit) {
            $folder_id = $this->getParameter('id');
            if (!is_numeric($folder_id))
                return null; // ochrana před útokem
            $params['exclude_id'] = $folder_id;
        }
        $folders = $m->selectBox(0, $params);
        $folders = [0 => '(hlavní větev)'] + $folders;
        
        $form->addHidden('typ', 'F');
        $form->addText('nazev', 'Název složky:', 50, 80)
                ->addRule(Nette\Forms\Form::FILLED);
        $form->addText('popis', 'Popis:', 50, 200);
        $form->addSelect('parent_id', 'Složka:', $folders);
        
        return $form;
    }
    
    protected function createComponentNovaSlozkaForm()
    {
        $form = $this->createFolderForm(false);

        $form->addSubmit('vytvorit', 'Vytvořit');
        $form->addSubmit('storno', 'Zrušit')
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'stornoClicked');

        $form->onSuccess[] = array($this, 'vytvoritClicked');

        return $form;
    }
    
    protected function createComponentUpravitSlozkuForm()
    {
        $form = $this->createFolderForm(true);
        
        $spis = isset($this->template->Spis) ? $this->template->Spis : null;
        if ($spis)
            foreach ($spis as $key => $value) {
                if (isset($form[$key]))
                    $form[$key]->setDefaultValue($value);
            }
            
        $form->addSubmit('upravit', 'Upravit')
                ->onClick[] = array($this, 'upravitClicked');
        $form->addSubmit('storno', 'Zrušit')
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'stornoClicked');

        return $form;
    }
}

class Spisovka_SpisyPresenter extends SpisyPresenter
{

    private $spis_plan;
    private $hledat;
    private $pdf_output = 0;

    public function startup()
    {
        $client_config = Nette\Environment::getVariable('client_config');
        $this->template->Typ_evidence = $client_config->cislo_jednaci->typ_evidence;
        $this->template->Oddelovac_poradi = $client_config->cislo_jednaci->oddelovac;
        parent::startup();
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
                    $content = preg_replace('#<table id="table_top">.*?</table>#s', '',
                            $content);

                    $mpdf = new mPDF('iso-8859-2', 'A4', 9, 'Helvetica');

                    $app_info = Nette\Environment::getVariable('app_info');
                    $app_info = explode("#", $app_info);
                    $app_name = (isset($app_info[2])) ? $app_info[2] : 'OSS Spisová služba v3';
                    $mpdf->SetCreator($app_name);
                    $mpdf->SetAuthor($this->user->getIdentity()->display_name);
                    $mpdf->SetTitle('Spisová služba - Detail spisu');

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
                    $content = preg_replace('#<table id="table_top">.*?</table>#s', '',
                            $content);

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
                    $mpdf->SetHeader('Seznam spisů||' . $this->template->Urad->nazev);
                    $mpdf->SetFooter("{DATE j.n.Y}/" . $this->user->getIdentity()->display_name . "||{PAGENO}/{nb}"); /* defines footer for Odd and Even Pages - placed at Outer margin */

                    $mpdf->WriteHTML($content);

                    $mpdf->Output('spisova_sluzba.pdf', 'I');
                }
            }
        }
    }

    public function renderVyber()
    {
        $this->template->dokument_id = $this->getParameter('id');
    }

    /**
     * Zobraz seznam spisu ve stavu "otevren", bez hledani, bez strankovani.
     * Vlozeni dokumentu do spisu.
     */
    public function renderStrom()
    {
        $this->template->dokument_id = $this->getParameter('dokument_id');

        $Spisy = new Spis();
        $args = ['where' => ['stav = 1']];
        $args = $Spisy->spisovka($args);
        $result = $Spisy->seznamRychly($args['where']);
        $result->setRowClass(null);
        $this->template->spisy = $result->fetchAll();
    }

    /**
     * Jako renderStrom(), ale neomezuj stav
     */
    public function renderStrom2()
    {
        $Spisy = new Spis();
        $args = $Spisy->spisovka(null);
        $result = $Spisy->seznamRychly($args['where']);
        $result->setRowClass(null);
        $this->template->spisy = $result->fetchAll();
    }

    public function renderSeznamAjax($q)
    {
        $Spisy = new Spis();
        $result = $Spisy->search($q);

        $this->sendJson($result);
    }

    // TODO: Zcela chybi kontrola opravneni
    public function actionVlozitDokument()
    {
        $spis_id = $this->getParameter('id', null);
        $dokument_id = $this->getParameter('dok_id', null);
        $Spisy = new Spis();

        $spis = $Spisy->getInfo($spis_id);
        if (!$spis || !$dokument_id)
            throw new Exception('Neplatný parametr');

        // Propojit s dokumentem
        $DokumentSpis = new DokumentSpis();
        $DokumentSpis->pripojit($dokument_id, $spis_id);

        $this->sendJson($spis);
    }

    // TODO: Zcela chybi kontrola opravneni
    public function actionVyjmoutDokument()
    {
        $ok = false;
        $dokument_id = $this->getParameter('dok_id', null);
        $DokumentSpis = new DokumentSpis();
        $spis = $DokumentSpis->spis($dokument_id);

        if ($spis) {
            $where = array(array('dokument_id=%i', $dokument_id));
            $DokumentSpis->odebrat($where);

            $Log = new LogModel();
            $Log->logDokument($dokument_id, LogModel::SPIS_DOK_ODEBRAN,
                    'Dokument vyjmut ze spisu "' . $spis->nazev . '"');
            $Log->logSpis($spis->id, LogModel::SPIS_DOK_ODEBRAN,
                    'Dokument "' . $dokument_id . '" odebran ze spisu');
            $ok = true;
        }

        $this->sendJson(['ok' => $ok]);
    }

    public function actionDefault()
    {
        
    }

    public function renderDefault($hledat = null)
    {
        $Spisy = new Spis();

        $args = null;
        if (!empty($hledat)) {
            $args = array('where' => array(array("tb.nazev LIKE %s", '%' . $hledat . '%')));
            $this->hledat = $hledat;
        }

        $client_config = Nette\Environment::getVariable('client_config');
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($client_config->nastaveni->pocet_polozek) ? $client_config->nastaveni->pocet_polozek
                    : 20;

        $args = $Spisy->spisovka($args);
        $predat_do_spisovny = $this->getParameter('do_spisovny', false);
        $order_by = null;
        if ($predat_do_spisovny) {
            $args['where'][] = 'tb.stav = ' . Spis::UZAVREN;
            $order_by = 'nazev';
        }
        $result = $Spisy->seznam($args, $order_by);
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
        }

        if (count($seznam) > 0) {
            $spis_ids = array();
            foreach ($seznam as &$spis) {
                $spis_ids[] = $spis->id;
                // v seznamu uzavřených spisů je zobraz zarovnaně, ignoruj umístění ve stromu
                if ($predat_do_spisovny)
                    $spis->uroven = 0;
            }
            $this->template->seznam_dokumentu = $Spisy->seznamDokumentu($spis_ids);
        } else {
            $this->template->seznam_dokumentu = array();
        }

        $this->template->seznam = $seznam;

        $SpisovyZnak = new SpisovyZnak();
        $spisove_znaky = $SpisovyZnak->select()->fetchAssoc('id');
        $this->template->SpisoveZnaky = $spisove_znaky;
    }

    public function renderDetail($id, $upravit)
    {
        $spis_id = $id;
        // Info o spisu
        $Spisy = new Spis();
        $this->template->Spis = $spis = $Spisy->getInfo($spis_id, true);

        if (!$spis) {
            // spis neexistuje nebo se nepodarilo nacist
            $this->setView('noexist');
            return;
        }

        $this->template->SpisZnak_nazev = "";
        if (!empty($spis->spisovy_znak_id)) {
            $SpisovyZnak = new SpisovyZnak();
            $sz = $SpisovyZnak->select(["[id] = $spis->spisovy_znak_id"])->fetch();
            $this->template->SpisZnak_nazev = $sz->nazev;
        }

        $opravneni = Spis::zjistiOpravneniUzivatele($spis);
        $this->template->Lze_prevzit = $opravneni['lze_prevzit'];
        $this->template->Lze_cist = $opravneni['lze_cist'];
        $this->template->Lze_menit = $opravneni['lze_menit'];
        $this->template->Editovat = $opravneni['lze_menit'] && $upravit == 'info';

        if (!$opravneni['lze_cist']) {
            $this->setView('noaccess');
            return;
        }

        //$client_config = Environment::getVariable('client_config');
        //$vp = new VisualPaginator($this, 'vp');
        //$paginator = $vp->getPaginator();
        //$paginator->itemsPerPage = isset($client_config->nastaveni->pocet_polozek)?$client_config->nastaveni->pocet_polozek:20;
        //$result = $DokumentSpis->dokumenty($spis_id, 1, $paginator);

        $DokumentSpis = new DokumentSpis();
        $this->template->seznam = $opravneni['lze_cist'] ? $DokumentSpis->dokumenty($spis_id) : null;

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

    public function actionPrevzit()
    {
        $spis_id = $this->getParameter('id', null);

        $Spisy = new Spis;
        $Spisy->getInfo($spis_id);

        $DokSpis = new DokumentSpis();
        $dokumenty = $DokSpis->dokumenty($spis_id);

        if (count($dokumenty) > 0) {
            // obsahuje dokumenty - predame i dokumenty
            $dokument = current($dokumenty);

            $Workflow = new Workflow();
            if ($Workflow->predany($dokument->id)) {
                if ($Workflow->prevzit($dokument->id)) {
                    $this->flashMessage('Úspěšně jste si převzal tento spis.');
                } else {
                    $this->flashMessage('Převzetí spisu do vlastnictví se nepodařilo. Zkuste to znovu.',
                            'warning');
                }
            } else {
                $this->flashMessage('Nemáte oprávnění k převzetí spisu.', 'warning');
            }
        } else {
            $orgjednotka_id = Orgjednotka::dejOrgUzivatele();

            if ($Spisy->zmenitOrg($spis_id, $orgjednotka_id)) {
                $this->flashMessage('Úspěšně jste si převzal tento spis.');
            } else {
                $this->flashMessage('Převzetí spisu do vlastnictví se nepodařilo. Zkuste to znovu.',
                        'warning');
            }
        }

        $this->redirect(':Spisovka:Spisy:detail', array('id' => $spis_id));
    }

    /* Tato operace je povolena pouze, kdyz spis nema zadneho vlastnika */

    public function renderPrivlastnit()
    {
        $spis_id = $this->getParameter('id', null);

        $orgjednotka_id = Orgjednotka::dejOrgUzivatele();

        $Spis = new Spis;
        $sp = $Spis->getInfo($spis_id);
        if (!empty($sp->orgjednotka_id) || !empty($sp->orgjednotka_id_predano))
            $this->flashMessage('Operace zamítnuta.', 'error');
        else if (!isset($orgjednotka_id))
            $this->flashMessage('Nemůžete převzít spis, protože nejste zařazen do organizační jednotky.',
                    'warning');
        else if ($Spis->zmenitOrg($spis_id, $orgjednotka_id)) {
            $this->flashMessage('Úspěšně jste si převzal tento spis. Pokud spis obsahoval dokumenty, jejich vlastnictví změněno nebylo.');
        } else {
            $this->flashMessage('Převzetí spisu do vlastnictví se nepodařilo. Zkuste to znovu.',
                    'warning');
        }

        $this->redirect(':Spisovka:Spisy:detail', array('id' => $spis_id));
    }

    public function renderZrusitprevzeti()
    {
        $spis_id = $this->getParameter('id', null);

        $Spisy = new Spis;
        $Spisy->getInfo($spis_id);

        $DokSpis = new DokumentSpis();
        $dokumenty = $DokSpis->dokumenty($spis_id);

        if (count($dokumenty) > 0) {
            // obsahuje dokumenty - predame i dokumenty
            $dokument = current($dokumenty);

            $Workflow = new Workflow();
            if ($Workflow->prirazeny($dokument->id)) {
                if ($Workflow->zrusit_prevzeti($dokument->id)) {
                    $this->flashMessage('Zrušil jste převzetí spisu.');
                } else {
                    $this->flashMessage('Zrušení převzetí se nepodařilo. Zkuste to znovu.',
                            'warning');
                }
            } else {
                $this->flashMessage('Nemáte oprávnění ke zrušení převzetí spisu.', 'warning');
            }
        } else {
            $Spis = new Spis;
            if ($Spis->zrusitPredani($spis_id)) {
                $this->flashMessage('Zrušil jste převzetí spisu.');
            } else {
                $this->flashMessage('Zrušení převzetí se nepodařilo. Zkuste to znovu.',
                        'warning');
            }
        }

        $this->redirect(':Spisovka:Spisy:detail', array('id' => $spis_id));
    }

    public function renderOdmitnoutprevzeti()
    {

        $spis_id = $this->getParameter('id', null);

        $Spisy = new Spis;
        $Spisy->getInfo($spis_id);

        $DokSpis = new DokumentSpis();
        $dokumenty = $DokSpis->dokumenty($spis_id);

        if (count($dokumenty) > 0) {
            // obsahuje dokumenty - predame i dokumenty
            $dokument = current($dokumenty);

            $Workflow = new Workflow();
            if ($Workflow->predany($dokument->id)) {
                if ($Workflow->zrusit_prevzeti($dokument->id)) {
                    $this->flashMessage('Odmítl jste převzetí spisu.');
                } else {
                    $this->flashMessage('Odmítnutí převzetí se nepodařilo. Zkuste to znovu.',
                            'warning');
                }
            } else {
                $this->flashMessage('Nemáte oprávnění k odmítnutí převzetí spisu.', 'warning');
            }
        } else {
            $Spis = new Spis;
            if ($Spis->zrusitPredani($spis_id)) {
                $this->flashMessage('Odmítl jste převzetí spisu.');
            } else {
                $this->flashMessage('Odmítnutí převzetí se nepodařilo. Zkuste to znovu.',
                        'warning');
            }
        }

        $this->redirect(':Spisovka:spisy:detail', array('id' => $spis_id));
    }

    public function createComponentBulkAction()
    {
        $BA = new Spisovka\Components\BulkAction();

        $actions = ['predat_spisovna' => 'předat do spisovny'];
        $BA->setActions($actions);
        $BA->setCallback([$this, 'bulkAction']);
        $BA->text_object = 'spis';

        return $BA;
    }

    public function bulkAction($action, $spisy)
    {
        $Spis = new Spis();
        switch ($action) {
            /* Predani vybranych spisu do spisovny  */
            case 'predat_spisovna':
                $count_ok = $count_failed = 0;
                foreach ($spisy as $spis_id) {
                    $result = $Spis->predatDoSpisovny($spis_id);
                    if ($result === true) {
                        $count_ok++;
                    } else {
                        $count_failed++;
                        foreach ($result as $msg)
                            $this->flashMessage($msg, 'warning');
                    }
                }

                if ($count_ok > 0)
                    $this->flashMessage(sprintf("Úspěšně jste předal $count_ok %s do spisovny.",
                                    Spis::cislovat($count_ok)));
                if ($count_failed > 0)
                    $this->flashMessage(sprintf($count_failed . ' %s se nepodařilo předat do spisovny.',
                                    Spis::cislovat($count_failed)), 'warning');
                break;
        }
    }

    public function actionPredatDoSpisovny($id)
    {
        $m = new Spis;
        $result = $m->predatDoSpisovny($id);
        if ($result === true)
            $this->flashMessage('Spis byl předán do spisovny.');
        else {
            foreach ($result as $msg)
                $this->flashMessage($msg, 'warning');
            $this->flashMessage('Spis se nepodařilo předat do spisovny.', 'warning');
        }

        $this->redirect('default');
    }

    public function actionOtevrit($id)
    {
        $m = new Spis();
        if ($m->zmenitStav($id, Spis::OTEVREN)) {
            $this->flashMessage('Spis byl otevřen.');
        } else {
            $this->flashMessage('Spis se nepodařilo otevřít.', 'error');
        }

        $this->redirect('detail', ['id' => $id]);
    }

    public function actionUzavrit($id)
    {
        $m = new Spis();
        $spis = $m->getInfo($id);
        $kontrola = $m->kontrola($spis);
        if ($kontrola)
            $this->flashMessage('Upozornění: Spis nemá vyplněny všechny povinné údaje.',
                    'warning');

        $stav = $m->zmenitStav($id, Spis::UZAVREN);
        if ($stav === -1) {
            $this->flashMessage('Spis nelze uzavřít. Jeden nebo více dokumentů nejsou vyřízeny.',
                    'warning');
        } else if ($stav) {
            $this->flashMessage('Spis byl uzavřen.');
        } else {
            $this->flashMessage('Spis se nepodařilo uzavřít.', 'error');
        }

        $this->redirect('detail', ['id' => $id]);
    }

    public function vytvoritAjaxClicked(Nette\Application\UI\Form $form, $data)
    {
        $Spisy = new Spis();

        try {
            $spis_id = $Spisy->vytvorit($data);
        } catch (Exception $e) {
            $form->addError("Spis se nepodařilo vytvořit.");
            $form->addError($e->getMessage());
            return;
        }

        $this->sendJson(['status' => 'OK', 'id' => $spis_id, 'name' => $data['nazev']]);
    }

    protected function createComponentSearchForm()
    {

        $hledat = !is_null($this->hledat) ? $this->hledat : '';

        $form = new Nette\Application\UI\Form();
        $form->addText('dotaz', 'Hledat:', 20, 100)
                ->setValue($hledat);
        $form['dotaz']->getControlPrototype()->title = "Hledat lze dle názvu spisu";

        $form->addSubmit('hledat', 'Hledat')
                ->onClick[] = array($this, 'hledatSimpleClicked');

        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = null;
        $renderer->wrappers['label']['container'] = null;
        $renderer->wrappers['control']['container'] = null;

        return $form;
    }

    public function hledatSimpleClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $this->redirect('this', array('hledat' => $data['dotaz']));
    }

    public function renderPrideleni()
    {
        // tento nefunkční hack by se měl z programu odstranit
        $this->flashMessage('Funkce byla z programu odstraněna.', 'error');
        $this->redirect(':Spisovka:Spisy:default');

        /*
          $Spisy = new Spis();
          $spis_id = null;

          $post = $this->getRequest()->getPost();
          if ( isset($post['spisorg_pridelit']) ) {
          if ( isset($post['orgvybran']) ) {
          $Spis = new Spis;
          foreach( $post['orgvybran'] as $orgvybran_spis => $orgvybran_org ) {
          if ( $Spis->zmenitOrg($orgvybran_spis, $orgvybran_org) ) {
          $this->flashMessage('Úspěšně jste si přidělil spis číslo '.$orgvybran_spis);
          } else {
          $this->flashMessage('Přidělení spisu číslo '.$orgvybran_spis.' se nepodařilo. Zkuste to znovu.','warning');
          }
          }
          }
          $this->redirect(':Spisovka:Spisy:default');
          }

          $args = null;
          if ( !empty($hledat) ) {
          $args = array( 'where'=>array(array("tb.nazev LIKE %s",'%'.$hledat.'%')));
          }

          $args = $Spisy->spisovka($args);
          $result = $Spisy->seznam($args, 5, $spis_id);

          $seznam = $result->fetchAll();

          if ( count($seznam)>0 ) {
          $spis_ids = array();
          foreach($seznam as $spis) {
          $spis_ids[] = $spis->id;
          }
          $this->template->seznam_dokumentu = $Spisy->seznamDokumentu($spis_ids);
          } else {
          $this->template->seznam_dokumentu = array();
          }

          $this->template->seznam = $seznam;

          $SpisovyZnak = new SpisovyZnak();
          $spisove_znaky = $SpisovyZnak->selectBox(11);
          $this->template->SpisoveZnaky = $spisove_znaky;
         */
    }

}
