<?php

class Spisovna_SpisyPresenter extends BasePresenter
{

    private $spis_plan;
    private $hledat;
    private $pdf_output = 0;

    protected function isUserAllowed()
    {
        return $this->user->isAllowed('Spisovna', 'cist_dokumenty');
    }

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
                    $mpdf->SetHeader('Seznam spisů ve spisovně||' . $this->template->Urad->nazev);
                    $mpdf->SetFooter("{DATE j.n.Y}/" . $this->user->getIdentity()->display_name . "||{PAGENO}/{nb}"); /* defines footer for Odd and Even Pages - placed at Outer margin */

                    $mpdf->WriteHTML($content);

                    $mpdf->Output('spisova_sluzba.pdf', 'I');
                }
            }
        }
    }

    public function actionDefault()
    {
        
    }

    public function renderDefault()
    {

        $post = $this->getRequest()->getPost();
        if (isset($post['hromadna_submit'])) {
            $this->actionAkce($post);
        }

        $Spisy = new Spis();
        $spis_id = null;

        $args = null;
        $this->hledat = $this->getParameter('hledat', null);

        if (!empty($this->hledat)) {
            $args = array('where' => array(array("tb.nazev LIKE %s", '%' . $this->hledat . '%')));
        }

        $client_config = Nette\Environment::getVariable('client_config');
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($client_config->nastaveni->pocet_polozek) ? $client_config->nastaveni->pocet_polozek
                    : 20;

        $args = $Spisy->spisovna($args);
        $result = $Spisy->seznam($args, 5, $spis_id);
        $paginator->itemCount = count($result);

        // Volba vystupu - web/tisk/pdf
        $tisk = $this->getParameter('print');
        $pdf = $this->getParameter('pdfprint');
        if ($tisk) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            //$seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $seznam = $result->fetchAll();

            $this->setLayout(false);
            $this->setView('print');
        } elseif ($pdf) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            $this->pdf_output = 1;
            //$seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $seznam = $result->fetchAll();

            $this->setLayout(false);
            $this->setView('print');
        } else {
            $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
        }

        if (count($seznam) > 0) {
            $spis_ids = array();
            foreach ($seznam as $spis) {
                $spis_ids[] = $spis->id;
            }
            $this->template->seznam_dokumentu = $Spisy->seznamDokumentu($spis_ids);
        } else {
            $this->template->seznam_dokumentu = array();
        }

        $this->template->seznam = $seznam;

        $SpisovyZnak = new SpisovyZnak();
        $spisove_znaky = $SpisovyZnak->select()->fetchAssoc('id');
        $this->template->SpisoveZnaky = $spisove_znaky;

        //$seznam = $Spisy->seznam(null, 0, $spis_id);
        //$this->template->seznam = $seznam;

        $this->template->akce_select = array(
        );
    }

    public function renderPrijem($hledat)
    {
        if (!$this->user->isAllowed('Spisovna', 'prijem_dokumentu'))
            $this->forward(':NoAccess:default');

        $post = $this->getRequest()->getPost();
        if (isset($post['hromadna_submit'])) {
            $this->actionAkce($post);
        }

        $Spisy = new Spis();
        $spis_id = null;

        $args = null;
        if (!empty($hledat)) {
            $args = array('where' => array(array("tb.nazev LIKE %s", '%' . $hledat . '%')));
        }

        $client_config = Nette\Environment::getVariable('client_config');
        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($client_config->nastaveni->pocet_polozek) ? $client_config->nastaveni->pocet_polozek
                    : 20;

        $args = $Spisy->spisovna_prijem($args);
        $result = $Spisy->seznam($args, 5, $spis_id);
        $paginator->itemCount = count($result);

        // Volba vystupu - web/tisk/pdf
        $tisk = $this->getParameter('print');
        $pdf = $this->getParameter('pdfprint');
        if ($tisk) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            //$seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $seznam = $result->fetchAll();

            $this->setLayout(false);
            $this->setView('print');
        } elseif ($pdf) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            $this->pdf_output = 1;
            //$seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
            $seznam = $result->fetchAll();

            $this->setLayout(false);
            $this->setView('print');
        } else {
            $seznam = $result->fetchAll($paginator->offset, $paginator->itemsPerPage);
        }

        if (count($seznam) > 0) {
            $spis_ids = array();
            foreach ($seznam as $spis) {
                $spis_ids[] = $spis->id;
            }
            $this->template->seznam_dokumentu = $Spisy->seznamDokumentu($spis_ids);
        } else {
            $this->template->seznam_dokumentu = array();
        }

        $this->template->seznam = $seznam;

        $SpisovyZnak = new SpisovyZnak();
        $spisove_znaky = $SpisovyZnak->select()->fetchAssoc('id');
        $this->template->SpisoveZnaky = $spisove_znaky;

        $this->template->akce_select = array(
            'prevzit_spisovna' => 'převzetí spisů do spisovny'
        );
    }

    public function renderDetail()
    {
        $spis_id = $this->getParameter('id', null);
        // Info o spisu
        $Spisy = new Spis();
        $this->template->Spis = $spis = $Spisy->getInfo($spis_id, true);

        if ($spis) {

            $this->template->SpisZnak_nazev = "";
            if (!empty($spis->spisovy_znak_id)) {
                $SpisovyZnak = new SpisovyZnak();
                $sz = $SpisovyZnak->select(["[id] = $spis->spisovy_znak_id"])->fetch();
                $this->template->SpisZnak_nazev = $sz->nazev;
            }

            $user = $this->user;
            $user_id = $user->getIdentity()->id;
            $pridelen = $predan = $accessview = false;
            $formUpravit = null;

            // prideleno
            if (Orgjednotka::isInOrg($spis->orgjednotka_id)) {
                $pridelen = true;
                $accessview = true;
            }
            // predano
            if (Orgjednotka::isInOrg($spis->orgjednotka_id_predano)) {
                $predan = true;
                $accessview = true;
            }

            if ($user->isInRole('superadmin')) {
                $accessview = 1;
                $pridelen = 1;
            }

            if (count($spis->workflow) > 0) {
                $wf_orgjednotka_prideleno = $spis->orgjednotka_id;
                $wf_orgjednotka_predano = $spis->orgjednotka_id_predano;
                $org_cache = array();
                foreach ($spis->workflow as $wf) {

                    if (isset($org_cache[$wf->orgjednotka_id])) {
                        $orgjednotka_expr = $org_cache[$wf->orgjednotka_id];
                    } else {
                        $orgjednotka_expr = Orgjednotka::isInOrg($wf->orgjednotka_id);
                        $org_cache[$wf->orgjednotka_id] = $orgjednotka_expr;
                    }

                    if (!$accessview) {
                        if (($wf->prideleno_id == $user_id || $orgjednotka_expr) && ($wf->stav_osoby < 100 || $wf->stav_osoby != 0)) {
                            $accessview = 1;
                        }
                    }

                    if (!$pridelen) {
                        if (($wf->prideleno_id == $user_id || $orgjednotka_expr) && ($wf->stav_osoby == 1 && $wf->aktivni == 1 )) {
                            $pridelen = 1;
                            $wf_orgjednotka_prideleno = $wf->orgjednotka_id;
                        }
                    }
                    if (!$predan) {
                        if (($wf->prideleno_id == $user_id || $orgjednotka_expr) && ($wf->stav_osoby == 0 && $wf->aktivni == 1 )) {
                            $predan = 1;
                            $wf_orgjednotka_predano = $wf->orgjednotka_id;
                        }
                    }

                    if ($predan && $pridelen && $accessview) {
                        break;
                    }
                }
            }

            $this->template->Pridelen = $pridelen;
            if ($pridelen) {
                $this->template->AccessEdit = 0;
                $formUpravit = null;
            }
            $this->template->Predan = $predan;
            $this->template->AccessView = $accessview;

            $Orgjednotka = new Orgjednotka();
            if (empty($spis->orgjednotka_id) && !empty($wf_orgjednotka_prideleno)) {
                $spis->orgjendotka_id = $wf_orgjednotka_prideleno;
                $spis->orgjendotka_prideleno = $Orgjednotka->getInfo($wf_orgjednotka_prideleno);
            }
            if (empty($spis->orgjednotka_id_predano) && !empty($wf_orgjednotka_predano)) {
                $spis->orgjendotka_id_predano = $wf_orgjednotka_predano;
                $spis->orgjendotka_predano = $Orgjednotka->getInfo($wf_orgjednotka_predano);
            }

            if ($accessview) {
                $DokumentSpis = new DokumentSpis();
                $result = $DokumentSpis->dokumenty($spis_id);
                $this->template->seznam = $result;
            } else {
                $this->template->seznam = null;
            }

            if ($user->isAllowed('Spisovna', 'zmenit_skartacni_rezim')) {
                $this->template->AccessEdit = 1;
                $formUpravit = $this->getParameter('upravit', null);
            } else {
                $this->template->AccessEdit = 0;
                $formUpravit = null;
            }

            $this->template->FormUpravit = $formUpravit;

            $this->template->upravitForm = $this['upravitForm'];
        } else {
            // spis neexistuje nebo se nepodarilo nacist
            $this->setView('noexist');
        }

        // Volba vystupu - web/tisk/pdf
        $tisk = $this->getParameter('print');
        $pdf = $this->getParameter('pdfprint');
        if ($tisk) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            $this->setLayout(false);
            $this->setView('printdetail');
        } elseif ($pdf) {
            @ini_set("memory_limit", PDF_MEMORY_LIMIT);
            $this->pdf_output = 2;
            $this->setLayout(false);
            $this->setView('printdetail');
        }
    }

    public function actionAkce($data)
    {
        if (isset($data['hromadna_akce'])) {
            $Spis = new Spis();
            switch ($data['hromadna_akce']) {
                /* Predani vybranych spisu do spisovny  */
                case 'prevzit_spisovna':
                    if (isset($data['spis_vyber'])) {
                        $count_ok = $count_failed = 0;
                        foreach ($data['spis_vyber'] as $spis_id) {
                            $stav = $Spis->pripojitDoSpisovny($spis_id);
                            if ($stav === true) {
                                $count_ok++;
                            } else {
                                if (is_string($stav)) {
                                    $this->flashMessage($stav, 'warning');
                                }
                                $count_failed++;
                            }
                        }
                        if ($count_ok > 0) {
                            $this->flashMessage('Úspěšně jste přijal ' . $count_ok . ' spisů do spisovny.');
                        }
                        if ($count_failed > 0) {
                            $this->flashMessage($count_failed . ' spisů se nepodařilo příjmout do spisovny!',
                                    'warning');
                        }
                        if ($count_ok > 0 && $count_failed > 0) {
                            $this->redirect('this');
                        }
                    }
                    break;
                default:
                    break;
            }
        }
    }

    public function actionStav()
    {

        $spis_id = $this->getParameter('id');
        $stav = $this->getParameter('stav');

        $Spis = new Spis();

        switch ($stav) {
            case 'uzavrit':
                if ($Spis->zmenitStav($spis_id, 0)) {
                    $this->flashMessage('Spis byl uzavřen.');
                } else {
                    $this->flashMessage('Spis se nepodařilo uzavřit.', 'error');
                }
                break;
            case 'otevrit':
                if ($Spis->zmenitStav($spis_id, 1)) {
                    $this->flashMessage('Spis byl otevřen.');
                } else {
                    $this->flashMessage('Spis se nepodařilo otevřít.', 'error');
                }
                break;
            default:
                break;
        }

        $this->redirect(':Spisovka:Spisy:detail', array('id' => $spis_id));
    }

    protected function createComponentUpravitForm()
    {
        $skar_znak = array('A' => 'A', 'S' => 'S', 'V' => 'V');

        $form1 = new Spisovka\Form();
        $form1->addHidden('id');
        $form1->addComponent(new SpisovyZnakComponent(), 'spisovy_znak_id');
        $form1->addSelect('skartacni_znak', 'Skartační znak:', $skar_znak);
        $form1->addText('skartacni_lhuta', 'Skartační lhuta: ', 5, 5)
                ->addRule(Spisovka\Form::INTEGER, 'Skartační lhůta musí být celé číslo.');

        if (isset($this->template->Spis)) {
            $spis = $this->template->Spis;
            $form1['id']->setValue($spis->id);
            $form1['spisovy_znak_id']->setValue($spis->spisovy_znak_id);
            $form1['skartacni_znak']->setValue($spis->skartacni_znak);
            $form1['skartacni_lhuta']->setValue($spis->skartacni_lhuta);
        }

        $form1->addSubmit('upravit', 'Upravit')
                ->onClick[] = array($this, 'upravitClicked');
        $form1->addSubmit('storno', 'Zrušit')
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'stornoClicked');

        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');

        $renderer = $form1->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form1;
    }

    public function upravitClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $Spisy = new Spis();

        $spis_id = $data['id'];
        unset($data['id']);

        $data['date_modified'] = new DateTime();
        $data['user_modified'] = $this->user->getIdentity()->id;

        //Nette\Diagnostics\Debugger::dump($data); exit;

        try {
            $Spisy->update($data, array(array('id=%i', $spis_id)));
            $this->flashMessage('Spis byl upraven.');
            $this->redirect(':Spisovna:Spisy:detail', array('id' => $spis_id));
        } catch (DibiException $e) {
            $this->flashMessage('Spis se nepodařilo upravit.', 'warning');
            $this->flashMessage('CHYBA: "' . $e->getMessage(), 'error_ext');
            $this->redirect(':Spisovna:Spisy:detail', array('id' => $spis_id));
            //Nette\Diagnostics\Debugger::dump($e);
        }
    }

    public function stornoClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();
        $spis_id = $data['id'];
        $this->redirect(':Spisovna:Spisy:detail', array('id' => $spis_id));
    }

    protected function createComponentNovyForm()
    {

        $Spisy = new Spis();

        $typ_spisu = Spis::typSpisu();
        $spisy = $Spisy->seznam(null, 1);

        $form1 = new Nette\Application\UI\Form();
        $form1->getElementPrototype()->id('spis-vytvorit');
        $form1->addHidden('dokument_id', $this->template->dokument_id);
        $form1->addSelect('typ', 'Typ spisu:', $typ_spisu);
        $form1->addText('nazev', 'Název spisu:', 50, 80)
                ->addRule(Nette\Forms\Form::FILLED, 'Název spisu musí být vyplněn!');
        $form1->addText('popis', 'Popis:', 50, 200);
        $form1->addSelect('spis_parent_id', 'Připojit k:', $spisy);

        $form1->addSubmit('vytvorit', 'Vytvořit')
                ->onClick[] = array($this, 'vytvoritClicked');

        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');

        $renderer = $form1->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form1;
    }

    public function vytvoritClicked(Nette\Forms\Controls\SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $Spisy = new Spis();
        $data['stav'] = 1;
        $data['date_created'] = new DateTime();
        $data['user_created'] = $this->user->getIdentity()->user_id;

        try {
            $Spisy->vytvorit($data);
            $this->flashMessage('Spis "' . $data['nazev'] . '"  byl vytvořen.');
            unset($data['dokument_id']);
            if (!$this->isAjax()) {
                //$this->redirect('this');
            } else {
                $this->invalidateControl('dokspis');
            }

            //$this->redirect(':Admin:Spisy:detail',array('id'=>$spis_id));
        } catch (DibiException $e) {
            $this->flashMessage('Spis "' . $data['nazev'] . '" se nepodařilo vytvořit. ' . $e->getMessage(),
                    'warning');
        }
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

        //$form1->onSubmit[] = array($this, 'upravitFormSubmitted');
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

}
