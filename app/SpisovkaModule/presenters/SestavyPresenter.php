<?php

class Spisovka_SestavyPresenter extends BasePresenter
{
    protected function isUserAllowed()
    {
        return Sestava::isUserAllowed();
    }
    
    public function renderDefault()
    {
        $user_config = Environment::getVariable('user_config');

        $vp = new VisualPaginator($this, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->itemsPerPage = isset($user_config->nastaveni->pocet_polozek) ? $user_config->nastaveni->pocet_polozek : 20;
        $paginator->itemCount = Sestava::getCount();
        
        $seznam = Sestava::getAll(array('offset' => $paginator->offset, 
                                  'limit' => $paginator->itemsPerPage,
                                  'order' => array('typ' => 'DESC', 'nazev')));
        $this->template->sestavy = $seznam;                
    }

    public function handleAutoComplete($text, $typ, $user=null, $org=null)
    {
        Spisovka_VyhledatPresenter::autoCompleteHandler($this, $text, $typ, $user, $org);
    }
    
    public function actionPdf()
    {
        $pc_od = $this->getParam('pc_od',null);
        $pc_do = $this->getParam('pc_do',null);  
        $d_od  = $this->getParam('d_od',null);
        $d_do  = $this->getParam('d_do',null);        
        $today = $this->getParam('d_today',null);
        $rok   = $this->getParam('rok',null);
        $pokracovat = $this->getParam('pokracovat', false);
        
        @ini_set("memory_limit",PDF_MEMORY_LIMIT);
        $sestava_id = $this->getParam('id',null);
        $this->forward('detail', 
                array('view'=>'pdf', 'id'=>$sestava_id,
                      'pc_od'=>$pc_od, 'pc_do'=>$pc_do, 
                      'd_od'=>$d_od, 'd_do'=>$d_do, 
                      'd_today'=>$today, 'rok'=>$rok, 'pokracovat'=>$pokracovat
                     ));
    }

    public function actionTisk()
    {
        $pc_od = $this->getParam('pc_od',null);
        $pc_do = $this->getParam('pc_do',null);  
        $d_od  = $this->getParam('d_od',null);
        $d_do  = $this->getParam('d_do',null);        
        $today = $this->getParam('d_today',null);
        $rok   = $this->getParam('rok',null);  
        
        $sestava_id = $this->getParam('id',null);
        $this->forward('detail', 
                array('view'=>'tisk','id'=>$sestava_id,
                      'pc_od'=>$pc_od, 'pc_do'=>$pc_do, 
                      'd_od'=>$d_od, 'd_do'=>$d_do, 
                      'd_today'=>$today, 'rok'=>$rok,                     
                     ));
    }

    public function renderDetail($view)
    {
        $Dokument = new Dokument();

        $sestava = new Sestava($this->getParam('id'));
        $this->template->Sestava = $sestava;

        // info
        $this->template->view = $view;


        // sloupce
        $sloupce_nazvy = array(
                'smer'=>'Směr',
                'cislo_jednaci'=>'Číslo jednací',
                'spis'=>'Název spisu',
                'datum_vzniku'=>'Datum doruč./vzniku',
                'subjekty'=>'Odesílatel / adresát',
                'cislo_jednaci_odesilatele'=>'Č.j. odesílatele',
                'pocet_listu'=>'Počet listů',
                'pocet_priloh'=>'Počet příloh',
                'pocet_nelistu'=>'Počet nelistů',
                'nazev'=>'Věc',
                'vyridil'=>'Přiděleno / Vyřídil',
                'zpusob_vyrizeni'=>'Způsob vyřízení',
                'datum_odeslani'=>'Datum odeslání',
                'spisovy_znak'=>'Spis. znak',
                'skartacni_znak'=>'Skart. znak',
                'skartacni_lhuta'=>'Skart. lhůta',
                'zaznam_vyrazeni'=>'Záznam vyřazení',
                'popis'=>'Popis',
                'poznamka_predani'=>'Poznámka k předání',
                'prazdny_sloupec'=>'',
            );
        $this->template->sloupce_nazvy = $sloupce_nazvy;


        $sloupce = array(
                '-1'=>'smer',
                '0'=>'cislo_jednaci',
                '1'=>'spis',
                '2'=>'datum_vzniku',
                '3'=>'subjekty',
                '4'=>'cislo_jednaci_odesilatele',
                '5'=>'pocet_listu',
                '6'=>'pocet_priloh',
                '7'=>'pocet_nelistu',
                '8'=>'nazev',
                '9'=>'vyridil',
                '10'=>'zpusob_vyrizeni',
                '11'=>'datum_odeslani',
                '12'=>'spisovy_znak',
                '13'=>'skartacni_znak',
                '14'=>'skartacni_lhuta',
                '15'=>'zaznam_vyrazeni',
                '16'=>'popis',
                '17'=>'poznamka_predani',
                '18'=>'prazdny_sloupec'
            );

        $zobr = isset($sestava->zobrazeni_dat) ? unserialize($sestava->zobrazeni_dat) : false;
        if ($zobr === false)
            $zobr = array();
        // nastav vychozi hodnoty
        if (!isset($zobr['sloupce_poznamka']))
            $zobr['sloupce_poznamka'] = false;
        if (!isset($zobr['sloupce_poznamka_predani']))
            $zobr['sloupce_poznamka_predani'] = false;
        if (!isset($zobr['sloupce_smer_dokumentu']))
            $zobr['sloupce_smer_dokumentu'] = true;
        if (!isset($zobr['sloupce_prazdny']))
            $zobr['sloupce_prazdny'] = false;

        if (!isset($zobr['zobrazeni_cas']))
            $zobr['zobrazeni_cas'] = false;
        if (!isset($zobr['zobrazeni_adresa']))
            $zobr['zobrazeni_adresa'] = false;

        if (!$zobr['sloupce_poznamka'])
            unset($sloupce[16]);
        if (!$zobr['sloupce_smer_dokumentu'])
            unset($sloupce[-1]);
        if (!$zobr['sloupce_poznamka_predani'])
            unset($sloupce[17]);
        if (!$zobr['sloupce_prazdny'])
            unset($sloupce[18]);
            
        $this->template->sloupce = $sloupce;
        $this->template->zobrazeni = $zobr;
        
        try {
            if ( empty( $sestava->parametry ) ) {
                $parametry = null;
                $args = array();
            } else {
                $parametry = unserialize($sestava->parametry);
                $args = $Dokument->paramsFiltr($parametry);
            }
        }
        catch (Exception $e) {
            $this->flashMessage("Sestavu nelze zobrazit: " . $e->getMessage(), 'warning');
            $this->redirect('default');
        }

        if ( !isset($args['order']) ) {
            $args['order'] = array('d.podaci_denik_poradi','d.nazev');
        }

        // vstup
        $pc_od = $this->getParam('pc_od');
        $pc_do = $this->getParam('pc_do');
        $d_od = $this->getParam('d_od');
        $d_do = $this->getParam('d_do');
        
        if ( $d_od ) {
            try {
                $d_od = date("Y-m-d", strtotime($d_od));
                //$d_od = new DateTime($this->getParam('d_od',null));
            } catch (Exception $e) {
                $d_od = null;
            }
        }
        if ( $d_do ) {
            try {
                $d_do = date("Y-m-d", strtotime($d_do)+86400 );
                //$d_do = new DateTime($this->getParam('d_do',null));
            } catch (Exception $e) {
                $d_do = null;
            }
        }
        
        $today = $this->getParam('d_today',null);
        // dnesek
        if ( !empty($today) ) {
            $d_od = date("Y-m-d");
            $d_do = date("Y-m-d",time()+86400);
        }
                
        $rok = $this->getParam('rok', null);
        $this->template->rok = !empty($rok) ? $rok : date('Y');

        // podaci denik
        if ( $sestava->id == 1 ) { // pouze na podaci denik, u jinych sestav zatim ne
        
            // P.L. V podacim deniku nemohou byt dokumenty, ktere nemaji c.j.
            $args['where'][] = 'd.cislo_jednaci IS NOT NULL';
            
            $user_config = Environment::getVariable('user_config');
            
            if ( isset($user_config->cislo_jednaci->typ_deniku) && $user_config->cislo_jednaci->typ_deniku == "org" ) {        

                    $user = Environment::getUser()->getIdentity();
                    $orgjednotka_id = Orgjednotka::dejOrgUzivatele();

                    if ( empty($orgjednotka_id) ) {
                        $org = null;
                    } else {
                        $Org = new Orgjednotka();
                        $org = $Org->getInfo($orgjednotka_id);
                    }            
                 
                    // jen zaznamy z vlastniho podaciho deniku organizacni jednotky
                    $args['where'][] = array('d.podaci_denik=%s',$user_config->cislo_jednaci->podaci_denik . (!empty($org)?"_".$org->ciselna_rada:""));            
                    
            }
        } // if sestava->id == 1
        
        // rok
        if ( !empty($rok) ) {
            $args['where'][] = array('d.podaci_denik_rok = %i',$rok);
        }

        // rozsah poradoveho cisla
        if ( !empty($pc_od) && !empty($pc_do) ) {
            $args['where'][] = array(
                                'd.podaci_denik_poradi >= %i AND ',$pc_od,
                                'd.podaci_denik_poradi <= %i',$pc_do
                               );
        } else if ( !empty($pc_od) && empty($pc_do) ) {
            $args['where'][] = array('d.podaci_denik_poradi >= %i',$pc_od);
        } else if ( empty($pc_od) && !empty($pc_do) ) {
            $args['where'][] = array('d.podaci_denik_poradi <= %i',$pc_do);
        }

        // rozsah datumu
        if ( !empty($d_od) && !empty($d_do) ) {
            $args['where'][] = array(
                                'd.datum_vzniku >= %s AND ',$d_od,
                                'd.datum_vzniku <= %s',$d_do
                               );
        } else if ( !empty($d_od) && empty($d_do) ) {
            $args['where'][] = array('d.datum_vzniku >= %s',$d_od);
        } else if ( empty($d_od) && !empty($d_do) ) {
            $args['where'][] = array('d.datum_vzniku <= %s',$d_do);
        }

        // vystup
        $args = $Dokument->sestavaOmezeniOrg($args);

        $result = $Dokument->seznam($args);
        $seznam = $result->fetchAll();

        if ( count($seznam)>0 ) {

            $mnoho = count($seznam) > ($view == 'pdf' ? 100 : 500);
            $this->template->pocet_dokumentu = count($seznam);
            
            if ( $mnoho && !$this->getParam('pokracovat', false) ) {

                $this->template->prilis_mnoho = 1;
                $seznam = array();
                
                $reload_url = Environment::getHttpRequest()->getOriginalUri()->getAbsoluteUri();
                if ( strpos($reload_url,'?') !== false ) {
                    $reload_url .= "&pokracovat=1";
                } else {
                    if (substr($reload_url, -1) != '/')
                        $reload_url .= '/';
                    $reload_url .= "?pokracovat=1";
                }
                $this->template->reload_url = $reload_url;

            } else {

                $dokument_ids = array();
                foreach ($seznam as $row)
                    $dokument_ids[] = $row->id;

                // $start_memory = memory_get_usage();
                $this->template->subjekty = DokumentSubjekt::subjekty2($dokument_ids);
                $this->template->d2s = DokumentSubjekt::dejAsociaci($dokument_ids);
                // Debug::dump("Pamet zabrana nahranim subjektu: " . (memory_get_usage() - $start_memory));

                $pocty_souboru = DokumentPrilohy::pocet_priloh($dokument_ids);
                
                $datumy_odeslani = DokumentOdeslani::datumy_odeslani($dokument_ids);
                                        
                foreach ($seznam as $index => $row) {
                    $dok = $Dokument->getInfo($row->id, '');
                    $id = $dok->id;
                    $dok->pocet_souboru = isset($pocty_souboru[$id]) ? $pocty_souboru[$id] : 0;
                    $dok->datum_odeslani = isset($datumy_odeslani[$id]) 
                                                ? $datumy_odeslani[$id] : '';
                    $seznam[$index] = $dok;
                }
                
                if ($view == 'pdf')
                    $this->setView('pdf');
            }

        } 
        
        $this->template->seznam = $seznam;
        $this->setLayout('print');


    }

    public function renderNova()
    {
        $this->template->form = $this['newForm'];
        $this->template->nadpis = 'Nová sestava';
               
        $user = Environment::getUser();
        $this->template->vidiVsechnyDokumenty = $user->isAllowed('Dokument', 'cist_vse');        
        $this->setView('form');
    }

    public function renderUpravit()
    {
        $sestava = new Sestava($this->getParam('id'));
        $this->template->sestava = $sestava;
        
        if (!$sestava->isModifiable()) {
            $this->flashMessage('Sestavu "'.$sestava->nazev.'" není možné měnit.', 'warning');
            $this->redirect(':Spisovka:Sestavy:default');
        }
        
        $this->template->form = $this['upravitForm'];
        $this->template->nadpis = 'Upravit sestavu';
        
        $user = Environment::getUser();
        $this->template->vidiVsechnyDokumenty = $user->isAllowed('Dokument', 'cist_vse');        
        $this->setView('form');
    }

    protected function createForm()
    {
        $typ_dokumentu = array();
        $typ_dokumentu = Dokument::typDokumentu(null,3);

        $typ_doruceni = array(
            '0'=>'všechny',
            '1'=>'pouze doručené přes elektronickou podatelnu',
            '2'=>'pouze doručené přes email',
            '3'=>'pouze doručené přes datovou schránkou',
            '4'=>'doručené mimo epodatelnu',
        );

        $typ_select = array();
        $typ_select = Subjekt::typ_subjektu(null,3);

        $stat_select = array();
        $stat_select = Subjekt::stat(null,3);

        $zpusob_doruceni = array();
        $zpusob_doruceni = Dokument::zpusobDoruceni(null, 3);

        $zpusob_odeslani = array();
        $zpusob_odeslani = Dokument::zpusobOdeslani(null, 3);

        $zpusob_vyrizeni = array();
        $zpusob_vyrizeni = Dokument::zpusobVyrizeni(null, 3);

        $spudalost_seznam = array();
        $spudalost_seznam = SpisovyZnak::spousteci_udalost(null, 3);

        $skartacni_znak = array('0'=>'jakýkoli znak','A'=>'A','V'=>'V','S'=>'S');

        $stav_dokumentu = array(
            ''=>'jakýkoli stav',
            '1'=>'nový / rozpracovaný',
            '2'=>'přidělen / předán',
            '3'=>'vyřizuje se',
            '4'=>'vyřízen',
            '5'=>'vyřazen'
            );

        $pridelen = array('0'=>'kdokoli','2'=>'přidělen','1'=>'předán');


        $form = new AppForm();

        $form->addText('sestava_nazev', 'Název sestavy:', 80, 100);
        $form->addTextArea('sestava_popis', 'Popis sestavy:', 80, 3);
        $form->addSelect('sestava_typ', 'Lze měnit? :', array('1'=>'upravitelná sestava','2'=>'pevná sestava'));
        $form->addCheckbox('sestava_filtr', 'Filtrovat? :');

        $form->addCheckbox('zobrazeni_cas', 'U datumů zobrazit i čas:');
        $form->addCheckbox('zobrazeni_adresa', 'Zobrazit adresy u subjektů:');
        $form->addCheckbox('sloupce_poznamka', 'Popis:');
        $form->addCheckbox('sloupce_poznamka_predani', 'Poznámka k předání:');
        $form->addCheckbox('sloupce_smer_dokumentu', 'Typ dokumentu (příchozí / vlastní):');
        $form->addCheckbox('sloupce_prazdny', 'Prázdný sloupec:');

        $form->addText('nazev', 'Věc:', 80, 100);
        $form->addTextArea('popis', 'Stručný popis:', 80, 3);
        $form->addText('cislo_jednaci', 'Číslo jednací:', 50, 50);
        $form->addText('spisova_znacka', 'Název spisu:', 50, 50);
        $form->addSelect('dokument_typ_id', 'Typ Dokumentu:', $typ_dokumentu);
        $form->addSelect('typ_doruceni', 'Způsob doručení:', $typ_doruceni);
        $form->addSelect('zpusob_doruceni_id', 'Způsob doručení:', $zpusob_doruceni);
        $form->addText('cislo_jednaci_odesilatele', 'Číslo jednací odesilatele:', 50, 50);
        $form->addText('cislo_doporuceneho_dopisu', 'Číslo doporučeného dopisu:', 50, 50);
        $form->addCheckbox('cislo_doporuceneho_dopisu_pouze', 'Pouze doporučené dopisy');
        $form->addDatePicker('datum_vzniku_od', 'Datum doručení/vzniku (od):', 10);
        $form->addText('datum_vzniku_cas_od', 'Čas doručení (od):', 10, 15);
        $form->addDatePicker('datum_vzniku_do', 'Datum doručení/vzniku do:', 10);
        $form->addText('datum_vzniku_cas_do', 'Čas doručení do:', 10, 15);
//nepouzito v sablone
//        $form->addText('pocet_listu', 'Počet listů:', 5, 10);
//        $form->addText('pocet_priloh', 'Počet příloh:', 5, 10);
        $form->addSelect('stav_dokumentu', 'Stav dokumentu:', $stav_dokumentu);

        $form->addTextArea('poznamka', 'Poznámka:', 80, 4);

        $form->addSelect('zpusob_vyrizeni', 'Způsob vyřízení:', $zpusob_vyrizeni);
        $form->addDatePicker('datum_vyrizeni_od', 'Datum vyřízení od:', 10);
        $form->addText('datum_vyrizeni_cas_od', 'Čas vyřízení od:', 10, 15);
        $form->addDatePicker('datum_vyrizeni_do', 'Datum vyřízení do:', 10);
        $form->addText('datum_vyrizeni_cas_do', 'Čas vyřízení do:', 10, 15);

        $form->addSelect('zpusob_odeslani', 'Způsob odeslání:', $zpusob_odeslani);
        $form->addDatePicker('datum_odeslani_od', 'Datum odeslání (od):', 10);
        $form->addText('datum_odeslani_cas_od', 'Čas odeslání (od):', 10, 15);
        $form->addDatePicker('datum_odeslani_do', 'Datum odeslání do:', 10);
        $form->addText('datum_odeslani_cas_do', 'Čas odeslání do:', 10, 15);

        $form->addComponent(new VyberPostovniZasilky(), 'druh_zasilky');
        
        $SpisovyZnak = new SpisovyZnak();
        $spisznak_seznam = $SpisovyZnak->select(2);

        $form->addComponent(new Select2Component('Spisový znak:', $spisznak_seznam), 'spisovy_znak_id');
        $form->addTextArea('ulozeni_dokumentu', 'Uložení dokumentu:', 80, 4);
        $form->addTextArea('poznamka_vyrizeni', 'Poznámka k vyřízení:', 80, 4);
        $form->addSelect('skartacni_znak','Skartační znak: ', $skartacni_znak);
        $form->addText('skartacni_lhuta','Skartační lhuta: ', 5, 5);
        $form->addSelect('spousteci_udalost','Spouštěcí událost: ', $spudalost_seznam);

        $form->addText('prideleno_text', 'Přiděleno:', 50, 255)
                ->getControlPrototype()->autocomplete = 'off';
        $form->addText('predano_text', 'Předáno:', 50, 255)
                ->getControlPrototype()->autocomplete = 'off';

        $form->addCheckbox('prideleno_osobne', 'Přiděleno na mé jméno');
        $form->addCheckbox('prideleno_na_organizacni_jednotku', 'Přiděleno na mou organizační jednotku');
        $form->addCheckbox('predano_osobne', 'Předáno na mé jméno');
        $form->addCheckbox('predano_na_organizacni_jednotku', 'Předáno na mou organizační jednotku');


        $form->addSelect('subjekt_type', 'Typ subjektu:', $typ_select);
        $form->addText('subjekt_nazev', 'Název subjektu, jméno, IČ:', 50, 255);
        $form->addText('adresa_ulice', 'Ulice / část obce:', 50, 48);
        $form->addText('adresa_mesto', 'Obec:', 50, 48);
        $form->addText('adresa_psc', 'PSČ:', 10, 10);

        $form->addText('subjekt_email', 'Email:', 50, 250);
        $form->addText('subjekt_telefon', 'Telefon:', 50, 150);
        $form->addText('subjekt_isds', 'ID datové schránky:', 10, 50);
    
        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'dl';
        $renderer->wrappers['label']['container'] = 'dt';
        $renderer->wrappers['control']['container'] = 'dd';

        return $form;
    }
    
    protected function createComponentNewForm()
    {
        $form = $this->createForm();

        $form->addSubmit('odeslat', 'Vytvořit')
                 ->onClick[] = array($this, 'vytvoritClicked');
        $form->addSubmit('storno', 'Zrušit')
                 ->setValidationScope(FALSE)
                 ->onClick[] = array($this, 'stornoClicked');

        return $form;
    }

    public function vytvoritClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();

        $sestava_data = $this->handleSubmit($data);

        try {
            Sestava::create('Sestava', $sestava_data);

            $this->flashMessage('Sestava "'.$sestava_data['nazev'].'" byla vytvořena.');
            $this->redirect(':Spisovka:Sestavy:default');
        } catch (DibiException $e) {
            $this->flashMessage('Sestavu "'.$sestava_data['nazev'].'" se nepodařilo vytvořit.','warning');
            $this->flashMessage('CHYBA: '. $e->getMessage(),'warning');
        }

    }

    protected function createComponentUpravitForm()
    {
        $sestava = @$this->template->sestava;
        
        $params = array();
        if ( isset($sestava->parametry) )
            $params = unserialize($sestava->parametry);
        $this->template->params = $params;
        if ( isset($sestava->zobrazeni_dat) && !empty($sestava->zobrazeni_dat))
            $params = array_merge($params, unserialize($sestava->zobrazeni_dat));        

        unset($params['prideleno'],$params['predano'],$params['prideleno_org'],$params['predano_org']);        

        $form = $this->createForm();
        
        $form->addHidden('id')
                ->setValue(@$sestava->id);
                
        if (isset($sestava->nazev)) {
            $form['sestava_nazev']->setValue($sestava->nazev);
            $form['sestava_popis']->setValue($sestava->popis);
            $form['sestava_typ']->setValue($sestava->typ);
            $form['sestava_filtr']->setValue($sestava->filtr);
        }

        if (isset($params['druh_zasilky']))
            $form['druh_zasilky']->setValue($params['druh_zasilky']);
        unset($params['druh_zasilky']);
        
        if (!empty($params))
            foreach($params as $key => $value)
                try {
                    $input = $form[$key];
                    if (is_a($input, 'Checkbox')) ;
                        // nedelej nic, framework provadi kontrolu parametru lepe
                        // $value = $value ? true : false;
                    $input->setValue($value);
                }
                catch (Exception $e) {
                }
                
        $form->addSubmit('odeslat', 'Upravit')
                 ->onClick[] = array($this, 'upravitClicked');
        $form->addSubmit('storno', 'Zrušit')
                 ->setValidationScope(FALSE)
                 ->onClick[] = array($this, 'stornoClicked');

        return $form;
    }

    protected function handleSubmit($data)
    {
        $sestava = array();
        $sestava['nazev'] = $data['sestava_nazev'];
        $sestava['popis'] = $data['sestava_popis'];
        $sestava['typ'] = $data['sestava_typ'];
        $sestava['filtr'] = ($data['sestava_filtr'])?1:0;

        unset($data['id'],$data['sestava_nazev'],$data['sestava_popis'],
              $data['sestava_typ'],$data['sestava_filtr']);

        // pro sestaveni sloupce
        $sloupce = '';
        $sestava['sloupce'] = $sloupce;

        // pro sestaveni parametru
        if ( isset($_POST['prideleno']) ) {
            $data['prideleno'] = $_POST['prideleno'];
        }
        if ( isset($_POST['predano']) ) {
            $data['predano'] = $_POST['predano'];
        }
        if ( isset($_POST['prideleno_org']) ) {
            $data['prideleno_org'] = $_POST['prideleno_org'];
        }
        if ( isset($_POST['predano_org']) ) {
            $data['predano_org'] = $_POST['predano_org'];
        }
        if ( isset($_POST['druh_zasilky']) ) {
            if ( count($_POST['druh_zasilky'])>0 ) {
                $druh_sql = array();
                foreach ( $_POST['druh_zasilky'] as $druh_id => $druh_zasilky ) {
                    $druh_sql[] = $druh_id;
                }
                $data['druh_zasilky'] = serialize($druh_sql);            
            }
        }          
        
        $zobrazeni_dat = array();
        $nazvy_poli = array('zobrazeni_cas', 'zobrazeni_adresa', 'sloupce_poznamka',
            'sloupce_poznamka_predani', 'sloupce_smer_dokumentu', 'sloupce_prazdny');
        foreach($nazvy_poli as $key) {
            $zobrazeni_dat[$key] = $data[$key];
            unset($data[$key]);
        }
        $sestava['zobrazeni_dat'] = serialize($zobrazeni_dat);
        
        $params = '';
        $params = serialize($data);
        $sestava['parametry'] = $params;

        return $sestava;
    }
    
    
    public function upravitClicked(SubmitButton $button)
    {
        $data = $button->getForm()->getValues();
        $id = $data['id'];
        $sestava_data = $this->handleSubmit($data);

        try {
            $sestava = new Sestava($id);
            $sestava->modify($sestava_data);
            $sestava->save();
            
            $this->flashMessage("Sestava '$sestava->nazev' byla upravena.");
        }
        catch (Exception $e) {
            $this->flashMessage("Sestavu '$sestava->nazev' se nepodařilo upravit.", 'warning');
            $this->flashMessage('Popis chyby: '. $e->getMessage(),'warning');
        }
        
        $this->redirect(':Spisovka:Sestavy:default');
    }


    public function stornoClicked(SubmitButton $button)
    {
        $this->redirect(':Spisovka:Sestavy:default');
    }

    public function actionSmazat()
    {
        $s = new Sestava($this->getParam('id'));
        
        try {
            $s->delete();
            $this->flashMessage('Sestava byla smazána.');
        }
        catch (Exception $e) {
            $this->flashMessage($e->getMessage(), 'warning');
        }
        
        $this->redirect(':Spisovka:Sestavy:default');
    }
}

