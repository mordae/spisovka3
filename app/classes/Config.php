<?php

namespace Spisovka;

interface IConfig
{
    /**
     * @return Spisovka\ArrayHash
     */
    function get();
    
    function save($data);
    
}

class Config implements IConfig
{

    protected $name;
    protected $data;

    public function __construct($name, $client_dir = null)
    {
        if (!in_array($name, array('epodatelna', 'klient', 'database')))
            throw new \InvalidArgumentException(__METHOD__ . "() - neplatný konfigurační soubor '$name'");

        if (!isset($client_dir))
            $client_dir = CLIENT_DIR;
        $ext = $name === 'database' ? 'neon' : 'ini';
        $loader = new \Nette\DI\Config\Loader();
        $array = $loader->load("$client_dir/configs/$name.$ext");
        $this->name = $name;
        $this->data = \Spisovka\ArrayHash::from($array);
    }
    
    /**
     * 
     * @return Spisovka\ArrayHash
     */
    public function get()
    {
        return $this->data;
    }

    public function save($data)
    {
        self::_saveCheckParameter($data);
        
        $loader = new \Nette\DI\Config\Loader();
        $loader->save($data, CLIENT_DIR . "/configs/{$this->name}.ini");
    }

    public static function _saveCheckParameter(&$data)
    {
        if (is_array($data))
            ;
        else if ($data instanceof \Spisovka\ArrayHash) {
            $data = $data->toArray();
        } else
            throw new InvalidArgumentException(__METHOD__ . '() - neplatný argument');        
    }
}

/**
 *  Tato trida cte konfiguraci ze souboru epodatelna.ini.
 *  Pouzito pri upgradu ze spisovky < 3.5.0
 */
class ConfigEpodatelnaOld extends Config
{

    public function __construct()
    {
        parent::__construct('epodatelna');
    }

    public function save($data)
    {
        // nedelej nic, nemelo by se vubec volat
    }
}

/**
 * Cte konfiguraci z databaze
 */
class ConfigEpodatelna implements IConfig
{
    
    public function get()
    {
        $data = \Settings::get('epodatelna', null);
        if (!$data)
            throw new Exception(__METHOD__ . '() - v databázi chybí nastavení e-podatelny');
        
        return \Spisovka\ArrayHash::from(unserialize($data));
    }
    
    public function save($data)
    {
        Config::_saveCheckParameter($data);
        \Settings::set('epodatelna', serialize($data));
    }                
}

class ConfigClient extends Config
{

    public function __construct()
    {
        parent::__construct('klient');
        
        if (!in_array($this->data->cislo_jednaci->typ_evidence, ['priorace', 'sberny_arch']))
            throw new \Exception (__METHOD__ . '() - chybné nastavení typu evidence');
        
        // Toto nastaveni nebylo v config.ini při instalaci aplikace
        // Týká se pouze sběrného archu
        if (empty($this->data->cislo_jednaci->oddelovac))
            $this->data->cislo_jednaci->oddelovac = '/';
    }
}

class ConfigDatabase extends Config
{

    public function __construct($client_dir)
    {
        parent::__construct('database', $client_dir);
    }

    public function save($data)
    {
        throw new \LogicException(__METHOD__ . '() - konfigurace databáze je pouze pro čtení');
    }

}
