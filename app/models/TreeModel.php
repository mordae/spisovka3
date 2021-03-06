<?php

abstract class TreeModel extends BaseModel
{

    const ORDERING_MAX_LENGTH = 30;

    protected $name;

    /**
     *  Slouží pro generování seznamu pro select box
     */
    protected $column_name = "nazev";

    /**
     *  Slouží pro generování pole sekvence_string
     */
    protected $column_ordering = "nazev";

    public function getInfo($id)
    {
        $row = $this->select(array(array('id=%i', $id)));
        $result = $row->fetch();
        return $result;
    }

    protected function getLevelExpression()
    {
        return "%sqlLENGTH(tb.sekvence) - LENGTH(REPLACE(tb.sekvence, '.', ''))";
    }

    /**
     * @param array $params
     * @return DibiResult
     */
    public function nacti($params = null)
    {
        $sql = array(
            'from' => array($this->name => 'tb'),
            'cols' => array('*', $this->getLevelExpression() => 'uroven'),
            'leftJoin' => array()
        );

        if (!empty($params['parent_id'])) {
            $parent_id = $params['parent_id'];
            $sql['leftJoin'] = array(
                'parent' => array(
                    'from' => array($this->name => 'tbp'),
                    'on' => array(array('tbp.id=%i', $parent_id))
                )
            );
            $sql['where'] = array(
                array("tb.sekvence LIKE CONCAT(tbp.sekvence,'.%')"),
                array("tb.id <> %i", $parent_id)
            );
        }

        $sql['order'] = array('tb.sekvence_string');

        if (is_array($params)) {
            if (isset($params['where'])) {
                if (isset($sql['where'])) {
                    $sql['where'] = array_merge($sql['where'], $params['where']);
                } else {
                    $sql['where'] = $params['where'];
                }
            }
            if (isset($params['order']))
                $sql['order'] = $params['order'];
            if (isset($params['leftJoin']))
                $sql['leftJoin'] = array_merge($sql['leftJoin'], $params['leftJoin']);
        }

        $result = $this->selectComplex($sql);
        return $result;
    }

    /**
     * Vytvoří uspořádaný seznam položek pro select box
     * @param int $type   0 - žádné další úpravy
     *                    1 - přidání položky '(hlavní větev)'
     *                    2 - výběr spis. znaku
     * @param array $params    slouží k filtrování položek (např. k výběru složek v tabulce "spisů")
     *                 $params['exclude_id]  slouží k vynechání položky z tímto ID ze seznamu
     *                 $params['parent_id']
     * @return array
     */
    public function selectBox($type = 0, $params = [])
    {
        $result = array();

        if ($type == 1) {
            $result[0] = '(hlavní větev)';
        } else if ($type == 2) {
            $result[0] = 'vyberte z nabídky ...';
        }

        $dibi_result = $this->nacti($params);

        $parent_sekvence = null;
        foreach ($dibi_result as $row) {
            if (isset($params['exclude_id']) && $row->id == $params['exclude_id']) {
                $parent_sekvence = $row->sekvence;
                continue;
            }

            if ($parent_sekvence && strpos($row->sekvence, $parent_sekvence) !== false)
                continue;

            $popis = "";
            if (!empty($row->popis))
                $popis = " - " . \Nette\Utils\Strings::truncate($row->popis, 90);
            $text = str_repeat("...", $row->uroven) . ' ' . $row->{$this->column_name} . $popis;

            if ($type == 2) {
                // spisové znaky
                // vrať pole HTML elementů kvůli funkci znemožnění výběru neaktivních položek
                $option = \Nette\Utils\Html::el('option')->value($row->id)->setHtml($text);
                if ($row->stav == 0)
                    $option->disabled(true);
                $result[$row->id] = $option;
            } else {
                $result[$row->id] = $text;
            }
        }

        return $result;
    }

    public function vlozitH($data)
    {
        if (empty($data['parent_id']))
            $data['parent_id'] = null;

        dibi::begin();
        try {
            // vlastní $data['sekvence_string'] určuje pouze model spisového znaku
            $sekvence_string = isset($data['sekvence_string']) ? $data['sekvence_string'] : substr($data[$this->column_ordering],
                            0, self::ORDERING_MAX_LENGTH);
            unset($data['sekvence_string']);

            // 1. clasic insert
            $id = $this->insert($data);

            // 2. update tree
            $parent_id = $data['parent_id'];
            $data_tree = array();
            if (empty($parent_id) || $parent_id == 0) {
                // is root node
                $data_tree['sekvence'] = $id;
                $data_tree['sekvence_string'] = $sekvence_string . '.' . $id;
            } else {
                // is subnode
                $parent = $this->select(array(array('id=%i', $parent_id)))->fetch();
                if (!$parent) {
                    dibi::rollback();
                    throw new InvalidArgumentException("TreeModel::vlozitH() - záznam ID $parent_id neexistuje.");
                }

                $data_tree['sekvence'] = $parent->sekvence . '.' . $id;
                $data_tree['sekvence_string'] = $parent->sekvence_string . '#' . $sekvence_string . '.' . $id;
            }
            $this->update($data_tree, array(array('id=%i', $id)));

            dibi::commit();
            return $id;
        } catch (Exception $e) {
            dibi::rollback();
            throw $e;
        }
    }

    public function upravitH($data, $id)
    {
        // 0. control param
        if (empty($id) || !is_numeric($id))
            throw new InvalidArgumentException('TreeModel::upravitH() - neplatný parameter "id"');

        // 1. clasic update
        dibi::begin();
        try {
            $sekvence_string = isset($data['sekvence_string']) ? $data['sekvence_string'] : substr($data[$this->column_ordering],
                            0, self::ORDERING_MAX_LENGTH);
            unset($data['sekvence_string']);

            $old_record = $this->select([['id = %i', $id]])->fetch();

            if (isset($data['spisovy_znak_format'])) {
                $part = explode(".", $old_record->{$this->column_ordering});
                if (count($part) > 0) {
                    foreach ($part as $pi => $pn) {
                        if (is_numeric($pn)) {
                            $part[$pi] = sprintf("%04d", $pn);
                        }
                    }
                }

                $info_nazev_sekvence = implode(".", $part);
                unset($data['spisovy_znak_format']);
            } else {
                $info_nazev_sekvence = substr($old_record->{$this->column_ordering},
                        0, self::ORDERING_MAX_LENGTH);
            }

            if ($data['parent_id'] == 0)
                $data['parent_id'] = null;

            $this->update($data, array(array('id=%i', $id)));

            // 2. update tree
            $parent_id = $data['parent_id'];

            $parent_id_old = $old_record->parent_id;
            if (empty($parent_id) && empty($parent_id_old)) {
                $parent_id = 999;
                $parent_id_old = 999;
            }

            $data_tree = array();

            if (empty($parent_id) && !empty($parent_id_old)) {
                // is root node

                $parent_old = $this->select(array(array('id=%i', $parent_id_old)))->fetch();
                if (!$parent_old) {
                    dibi::rollback();
                    throw new InvalidArgumentException("TreeModel::upravitH() - záznam ID $parent_id_old neexistuje.");
                }

                $data_tree['sekvence'] = $id;
                $data_tree['sekvence_string'] = $sekvence_string . '.' . $id;
                $this->update($data_tree, array(array('id=%i', $id)));

                // change child nodes
                $data_node = array();
                $data_node['sekvence%sql'] = "REPLACE(sekvence,'" . $parent_old->sekvence . '.' . $id . "','" . $id . "')";
                $data_node['sekvence_string%sql'] = "REPLACE(sekvence_string,'" . $parent_old->sekvence_string . "#" . $info_nazev_sekvence . "." . $id . "','" . $sekvence_string . "." . $id . "')";

                $this->update($data_node,
                        array(array("sekvence LIKE %s", $parent_old->sekvence . '.' . $id . ".%")));
            } else if ($parent_id != $parent_id_old && empty($parent_id_old)) {
                // change parent from root
                $parent_new = $this->select(array(array('id=%i', $parent_id)))->fetch();
                if (!$parent_new) {
                    dibi::rollback();
                    throw new InvalidArgumentException("TreeModel::upravitH() - záznam ID $parent_id neexistuje.");
                }

                $data_tree['sekvence'] = $parent_new->sekvence . '.' . $id;
                $data_tree['sekvence_string'] = $parent_new->sekvence_string . '#' . $sekvence_string . '.' . $id;
                $this->update($data_tree, array(array('id=%i', $id)));

                // change child nodes
                $data_node = array();
                $data_node['sekvence%sql'] = "REPLACE(sekvence,'" . $id . "','" . $parent_new->sekvence . '.' . $id . "')";
                $data_node['sekvence_string%sql'] = "REPLACE(sekvence_string,'" . $info_nazev_sekvence . "." . $id . "','" . $parent_new->sekvence_string . "#" . $sekvence_string . "." . $id . "')";
                $this->update($data_node, array(array("sekvence LIKE %s", $id . ".%")));
            } else if ($parent_id != $parent_id_old) {
                // change parent
                $parent_old = $this->select(array(array('id=%i', $parent_id_old)))->fetch();
                $parent_new = $this->select(array(array('id=%i', $parent_id)))->fetch();
                if (!$parent_new) {
                    dibi::rollback();
                    throw new InvalidArgumentException("TreeModel::upravitH() - záznam ID $parent_id neexistuje.");
                }

                $data_tree['sekvence'] = $parent_new->sekvence . '.' . $id;
                $data_tree['sekvence_string'] = $parent_new->sekvence_string . '#' . $sekvence_string . '.' . $id;
                $this->update($data_tree, array(array('id=%i', $id)));

                // change child nodes
                $data_node = array();
                $data_node['sekvence%sql'] = "REPLACE(sekvence,'" . $parent_old->sekvence . '.' . $id . "','" . $parent_new->sekvence . '.' . $id . "')";
                $data_node['sekvence_string%sql'] = "REPLACE(sekvence_string,'" . $parent_old->sekvence_string . "#" . $info_nazev_sekvence . "." . $id . "','" . $parent_new->sekvence_string . "#" . $sekvence_string . "." . $id . "')";
                $this->update($data_node,
                        array(array("sekvence LIKE %s", $parent_old->sekvence . '.' . $id . ".%")));
            } else {
                // nochange parent

                $data_node = array();
                $data_node['sekvence_string%sql'] = "REPLACE(sekvence_string,'" . $info_nazev_sekvence . "." . $id . "','" . $sekvence_string . "." . $id . "')";
                $this->update($data_node,
                        array(array("sekvence_string LIKE %s", "%" . $info_nazev_sekvence . "." . $id . "%")));
            }

            dibi::commit();
        } catch (Exception $e) {
            dibi::rollback();
            throw $e;
        }
    }

    /* Vraci: true - uspech
      false - neuspech, selhala kontrola cizího klíče
      Nebo hodí výjimku

      delete_children true - podrizene uzly se smazou (pokud je to mozne)
      funguje jenom pro jednu uroven potomku!
      false - podrizene uzly se presunou pod noveho rodice

      Je volano nyni pouze z modelu spisoveho znaku.
     */

    public function odstranitH($id, $delete_children)
    {
        $info = $this->getInfo($id);
        if (!$info)
            return false;

        if ($delete_children) {

            dibi::begin();
            try {
                $this->delete(array("sekvence LIKE %s", $info->sekvence . ".%"));
                $this->delete(array("id=%i", $id));
                dibi::commit();
                return true;
            } catch (Exception $e) {
                dibi::rollback();
                if ($e->getCode() == 1451)
                    return false;
                throw $e;
            }
        } else {

            dibi::begin();
            try {
                $data_node = array();
                if (empty($info->parent_id)) {
                    // parent is root
                    $data_node['sekvence%sql'] = "REPLACE(sekvence,'" . $info->sekvence . ".','')";
                    $data_node['sekvence_string%sql'] = "REPLACE(sekvence_string,'" . $info->sekvence_string . "#','')";
                } else {
                    $parent_info = $this->getInfo($info->parent_id);
                    // change child nodes
                    $data_node['sekvence%sql'] = "REPLACE(sekvence,'" . $info->sekvence . "','" . $parent_info->sekvence . "')";
                    $data_node['sekvence_string%sql'] = "REPLACE(sekvence_string,'" . $info->sekvence_string . "','" . $parent_info->sekvence_string . "')";
                }
                //Nette\Diagnostics\Debugger::dump($data_node); exit;

                $this->update($data_node,
                        array(array("sekvence LIKE %s", $info->sekvence . ".%")));

                $this->update(array('parent_id' => $info->parent_id),
                        array("parent_id = " . $info->id));

                $this->delete(array("id=%i", $id));
                dibi::commit();
                return true;
            } catch (Exception $e) {
                dibi::rollback();
                if ($e->getCode() == 1451)
                    return false;
                throw $e;
            }
        }
    }

    private function make_string($pass_len = 8)
    {
        $salt = 'abcdefghijklmnopqrstuvwxyz';
        $salt = strtoupper($salt);
        $salt_len = strlen($salt);
        /* function make_seed()
          {
          list($usec, $sec) = explode(' ', microtime());
          return (float) $sec + ((float) $usec * 100000);
          } */
        mt_srand(make_seed());
        $pass = '';
        for ($i = 0; $i < $pass_len; $i++) {
            $pass .= substr($salt, mt_rand() % $salt_len, 1);
        }
        return $pass;
    }

    /**
     * Opraví případné chyby v zobrazení stromu tím, že znovu vytvoří pomocné informace
     * v polích "sekvence" a "sekvence_string".
     * @return boolean
     * @throws Exception
     */
    public function rebuildIndex()
    {
        try {
            dibi::begin();
            $res = dibi::query("SELECT id, parent_id, {$this->column_ordering} AS order_by FROM {$this->name}");
            $data = $res->fetchAssoc('id');

            $processed = [];

            foreach ($data as $id => &$row) {
                if ($row->parent_id !== null)
                    continue;
                $processed[$id] = true;
                $row->sekvence = $id;
                $row->sekvence_string = substr($row->order_by, 0, self::ORDERING_MAX_LENGTH) . '.' . $id;
            }

            do {
                $found_one = false;
                foreach ($data as $id => &$row) {
                    if (isset($processed[$id]))
                        continue;
                    if (!isset($processed[$row->parent_id]))
                        continue; // parent node has not been processed
                    $processed[$id] = true;
                    $found_one = true;
                    $row->sekvence = $data[$row->parent_id]->sekvence . '.' . $id;
                    $row->sekvence_string = $data[$row->parent_id]->sekvence_string . '#'
                            . substr($row->order_by, 0, self::ORDERING_MAX_LENGTH) . '.' . $id;
                }
            } while ($found_one);

            foreach ($data as $id => $row) {
                dibi::query("UPDATE [{$this->name}] SET [sekvence] = %s, [sekvence_string] = %s WHERE [id] = $id",
                        $row->sekvence, $row->sekvence_string);
            }
            
            dibi::commit();
            
            return true;
        } catch (Exception $e) {
            dibi::rollback();
            throw $e;
        }

    }

}
