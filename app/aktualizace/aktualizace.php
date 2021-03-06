<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
    <title>Spisová služba - Aktualizace</title>
    <link rel="stylesheet" type="text/css" media="screen" href="<?php echo PUBLIC_URI; ?>css/site.css" />
    <link rel="stylesheet" type="text/css" media="screen" href="<?php echo PUBLIC_URI; ?>css/install_site.css" />
    <link rel="shortcut icon" href="<?php echo PUBLIC_URI; ?>favicon.ico" type="image/x-icon" />
</head>
<body>
<div id="layout_top">
    <div id="top">
        <h1>Spisová služba<span id="top_urad">Aktualizace</span></h1>
        <div id="top_menu">
            &nbsp;
        </div>
        <div id="top_jmeno">
            &nbsp;
        </div>
    </div>
</div>
<div id="layout">
<?php

function error($message)
{
    echo "<div class=\"error\">$message</div>";
}

function my_assert_handler($file, $line, $code)
{
    error("Kontrola selhala: $code (řádek $line)");
}

try {
    assert_options(ASSERT_ACTIVE, 1);
    assert_options(ASSERT_BAIL, 1);
    assert_options(ASSERT_WARNING, 0);
    assert_options(ASSERT_CALLBACK, 'my_assert_handler');
    ini_set('display_errors', 1);
    set_time_limit(0);

    define('VENDOR_DIR', dirname(APP_DIR) . '/vendor');
    require VENDOR_DIR . '/autoload.php';

    $loader = new Nette\Loaders\RobotLoader();
    $loader->addDirectory(APP_DIR);
    // neukladej nikam cache
    $loader->setCacheStorage(new Nette\Caching\Storages\DevNullStorage());
    $loader->register();

    define('UPDATE_DIR', APP_DIR . '/aktualizace/');

    require 'is_installed.php';

    Updates::init();

    $res = Updates::find_updates();
    $revisions = $res['revisions'];
    $alter_scripts = $res['alter_scripts'];
    $descriptions = $res['descriptions'];
    assert('count($revisions) > 0');

    $clients = Updates::find_clients();
} catch (Exception $e) {
    error($e->getMessage());
    die;
}

$do_update = isset($_GET['go']);

echo '    <div id="menu">';
if ($do_update)
    echo '<a href="aktualizace.php">Znovu zkontrolovat</a>';
else
    echo '<a href="aktualizace.php?go=1" onclick="return confirm(\'Opravdu chcete provést aktualizaci spisové služby?\');">Spustit aktualizaci</a>';
echo '    </div>
    <div id="content">';
        
foreach ($clients as $site_path => $site_name) {

    echo "<div class='update_site'>";
    echo "<h1>$site_name</h1>";

    try {
        $client = new Client_To_Update($site_path);

        $db_config = $client->get_db_config();

        echo '<div class="dokument_blok">';
        echo '<dl>';
        echo '    <dt>Databáze:</dt>';
        echo '    <dd>' . $db_config['driver'] . '://' . $db_config['username'] . '@' . $db_config['host'] . '/' . $db_config['database'] . '&nbsp;</dd>';
        echo '</dl>';

        $client->connect_to_db();

        $client_revision = $client->get_revision_number();
    } catch (Exception $e) {
        error($e->getMessage());
        continue;  // jdi na dalsiho klienta
    }

    // dibi::getProfiler()->setFile(UPDATE_DIR.'log.sql');   nefunkcni, v adresari s db aktualizacemi nelze zapisovat

    echo '<dl>';
    echo '    <dt>Poslední zjištěná revize klienta:</dt>';
    echo '    <dd>' . $client_revision . '&nbsp;</dd>';
    echo '</dl>';

    echo '</div>';
    echo '<br />';

    $found_update = false;
    $rev_error = false;

    foreach ($revisions as $rev) {
        if ($rev > $client_revision) {

            try {

                // Check skripty se pouzivaly v minulosti, kdy nebylo mozne spolehlive urcit revizi klienta
                $function_name = "is_installed_{$rev}";
                if (function_exists($function_name)) {
                    try {
                        if ($function_name())
                            continue; // aktualizace byla jiz provedena a pokracuje se dalsi aktualizaci
                    } catch (Exception $e) {
                        error("Při provádění kontrolního skriptu došlo k chybě!<br />Popis chyby: " . $e->getCode() . ' - ' . $e->getMessage());
                        // Je pravděpodobné, že tuto revizi nemá klient nainstalovánu
                        $found_update = true;
                        // Ukonči kontrolu revizí a přeskoč na dalšího klienta
                        break;
                    }
                }

                $found_update = true;

                echo "<div class='update_rev'>";

                // poznamka: transakce nefunguji ocekavanym zpusobem, meni-li se pri nich databazova struktura (coz dela vetsina aktualizaci)
                if ($do_update)
                    dibi::begin();

                // Info
                echo "<div class='update_title'>Informace o tomto aktualizačním kroku:</div>";
                echo "<div class='update_info'><strong>Revize #" . $rev . "</strong></div>";

                if (isset($descriptions[$rev]))
                    echo "<div class='update_info'>{$descriptions[$rev]}</div>";

                // php z nepochopitelnych duvodu hlasi warning, neni-li pouzit @
                @include_once UPDATE_DIR . $rev . '_code.php';

                $function_name = "revision_{$rev}_check";
                if (function_exists($function_name) && $do_update) {
                    try {
                        echo "<pre>";
                        $res = $function_name();
                        echo "</pre>";
                    } catch (Exception $e) {
                        $msg = "Při provádění kontroly došlo k chybě! Aktualizaci není možné provést.<br />Popis chyby: ";
                        if ($e->getCode())
                            $msg .= $e->getCode() . ' - ';
                        $msg .= $e->getMessage();
                        error($msg);
                        $res = false;
                    }
                    if ($res === false) {
                        // Ukonči aktualizaci a přeskoč na dalšího klienta
                        $rev_error = true;
                        break;
                    }
                }

                // PRE script - není aktuálně použito. Též chybí ošetření případných chyb
                $function_name = "revision_{$rev}_before";
                if (function_exists($function_name)) {
                    if ($do_update) {
                        echo "<div class='update_title'>Provedení PHP skriptu (před aktualizaci databáze)</div>";
                        echo "<pre>";
                        $function_name();
                        echo "</pre>";
                    } else {
                        echo "<div class='update_title'>Bude proveden PHP skript (před aktualizaci databáze)</div>";
                    }
                }

                // SQL
                if (isset($alter_scripts[$rev]) && count($alter_scripts[$rev]) > 0) {

                    if ($do_update) {
                        echo "<div class='update_title'>Aktualizace databáze:</div>";
                    } else {
                        echo "<div class='update_title'>Bude provedena aktualizace databáze s následujícími SQL příkazy:</div>";
                    }

                    echo "<pre>";
                    foreach ($alter_scripts[$rev] as $query) {
                        $query = str_replace("{tbls3}", $db_config['prefix'], $query);

                        if ($do_update) {
                            try {
                                dibi::query($query);
                                echo "<span style='color:green'> >> " . $query . "</span>\n";
                            } catch (DibiException $e) {
                                echo "<span style='color:red'> >> " . $query . "</span>\n";
                                throw $e;
                            }
                        } else {
                            echo "$query\n";
                        }
                    }
                    echo "</pre>";
                }

                // AFTER source
                $function_name = "revision_{$rev}_after";
                if (function_exists($function_name)) {
                    if ($do_update) {
                        echo "<div class='update_title'>Provedení PHP skriptu</div>";
                        echo "<pre>";
                        $function_name();
                        echo "</pre>";
                    } else {
                        echo "<div class='update_title'>Bude proveden PHP skript</div>";
                    }
                }

                if ($do_update) {
                    dibi::commit();
                    // Je nutne provest zaznam po kazde uspesne aktualizaci pro pripad, ze by nektera z aktualizaci byla neuspesna
                    $client->update_revision_number($rev);
                }
            } catch (DibiException $e) {
                if ($do_update) {
                    dibi::rollback();
                    error("Došlo k databázové chybě, aktualizace neproběhla úspěšně!<br />Popis chyby: " . $e->getCode() . ' - ' . $e->getMessage());
                    $rev_error = true;
                }
                break;
            } catch (Exception $e) {
                if ($do_update)
                    dibi::rollback();
                error("Došlo k neočekávané výjimce, ukončuji program.<br />Popis chyby: " . $e->getMessage());
                die();
            }

            echo "</div>";
        }
    }

    if (!$found_update) {
        echo "<div class='update_no'>Nebyla zjištěna žádná aktualizace. Spisová služba je aktuální.</div>";
    }

    if ($do_update) {
        if (!$rev_error)
            if (!$client->update_revision_number($rev))
                error("Upozornění: nepodařilo se zapsat nové číslo verze do souboru _aktualizace");


        // vymazeme temp od robotloader, templates a cache
        // Nemazat temp, pokud zadna aktualizace nebyla provedena
        if ($found_update)
            deleteDir($client->get_path() . "/temp/");
    }


    dibi::disconnect();

    if ($rev_error)
        break; // Preskoc ostatni klienty - chyba muze byt vazna

    if ($do_update && $found_update)
        if (!$rev_error)
            echo "<p>Aktualizace klienta proběhla úspěšně.</p>";
        else
            echo "<p>Aktualizace klienta nebyla dokončena.</p>";

    echo "</div>\n\n";
}

// Konec php programu


function deleteDir($dir, $dir_parent = null)
{
    if (substr($dir, strlen($dir) - 1, 1) != '/')
        $dir .= '/';

    if (empty($dir) || $dir == "/" || $dir == "." || $dir == "..") {
        // zamezeni aspon zakladnich adresaru, ktere mohou delat neplechu
        return false;
    }

    // potlac pripadne varovani, je mozne, ze do adresare nemame pristup (pripad hostingu)
    if ($handle = @opendir($dir)) {
        while ($obj = readdir($handle)) {
            // git neumi spravovat prazdne adresare, proto v adresari temp je stub soubor .gitignore
            if ($obj != '.' && $obj != '..' && $obj != '.gitignore') {
                if (is_dir($dir . $obj)) {
                    if (!deleteDir($dir . $obj, $dir))
                        return false;
                }
                elseif (is_file($dir . $obj)) {
                    if (!@unlink($dir . $obj))
                        return false;
                }
            }
        }
        closedir($handle);

        if (!is_null($dir_parent)) {
            if (!@rmdir($dir))
                return false;
        }
        return true;
    }
    return false;
}

?>
    </div>
</div>
<div id="layout_bottom">
    <div id="bottom">
        <strong>OSS Spisová služba</strong><br/>
        Na toto dílo se vztahuje licence EUPL v.1.1</a>
    </div>
</div>


</body>
</html>
