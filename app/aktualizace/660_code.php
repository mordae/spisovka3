<?php

function revision_660_check()
{
/*  Bohužel jedinečnost názvu spisů a spisových znaků nemůžeme pro stávající uživatele
    aplikace vynucovat.
    
    // --------------------------------------------------------------------
    $result = dibi::query('SELECT pocet FROM (SELECT COUNT(`nazev`) AS pocet FROM [:PREFIX:spisovy_znak] GROUP BY `nazev`) subq WHERE pocet > 1');
    
    if (count($result))
        throw new Exception('Kontrola integrity dat selhala. Název spisového znaku musí být jedinečný!');

    // --------------------------------------------------------------------    
    $result = dibi::query('SELECT pocet FROM (SELECT COUNT(`nazev`) AS pocet FROM [:PREFIX:spis] GROUP BY `nazev`) subq WHERE pocet > 1');
    
    if (count($result))
        throw new Exception('Kontrola integrity dat selhala. Název spisu musí být jedinečný!');
*/

    // --------------------------------------------------------------------    
    $result = dibi::query('SELECT pocet FROM (SELECT COUNT(`code`) AS pocet FROM [:PREFIX:user_role] GROUP BY `code`) subq WHERE pocet > 1');
    
    if (count($result))
        throw new Exception('Kontrola integrity dat selhala. Kód role musí být jedinečný!');

    // --------------------------------------------------------------------
    $result = dibi::query('SELECT pocet FROM (SELECT COUNT(`dokument_id`) AS pocet FROM [:PREFIX:dokument_to_spis] GROUP BY `dokument_id`) subq WHERE pocet > 1');
    
    if (count($result))
        throw new Exception('Kontrola integrity dat selhala. Dokument nemůže být zařazen do více spisů!');

    // --------------------------------------------------------------------
    $result = dibi::query('SELECT pocet FROM (SELECT COUNT(`user_id`) AS pocet FROM [:PREFIX:user_to_role] GROUP BY `user_id`, `role_id`) subq WHERE pocet > 1');
    
    if (count($result))
        throw new Exception('Kontrola integrity dat selhala. Uživatel nemůže mít určitou roli přiřazenu vícekrát!');
    
    // --------------------------------------------------------------------
    $result = dibi::query('SELECT pocet FROM (SELECT COUNT(`role_id`) AS pocet FROM [:PREFIX:user_acl] GROUP BY `role_id`, `rule_id`) subq WHERE pocet > 1');
    
    if (count($result))
        throw new Exception('Kontrola integrity dat selhala. Uživatel nemůže mít určitou roli přiřazenu vícekrát!');
    
    
}

function revision_660_after()
{
    // zkus zmenit db strukturu, ale ignoruj, pokud by databaze hlasila chybu - kdyby zmena nebyla mozna
    try {
        dibi::query('ALTER TABLE `:PREFIX:spisovy_znak` ADD UNIQUE KEY `nazev` (`nazev`)');
    }
    catch (Exception $e) {
    }
    
    try {
        dibi::query('ALTER TABLE `:PREFIX:spis` ADD UNIQUE KEY `nazev` (`nazev`)');
    }
    catch (Exception $e) {
    }
}

?>