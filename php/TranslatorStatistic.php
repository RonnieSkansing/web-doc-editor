<?php

require_once dirname(__FILE__) . '/AccountManager.php';
require_once dirname(__FILE__) . '/DBConnection.php';
require_once dirname(__FILE__) . '/RepositoryManager.php';

class TranslatorStatistic
{
    private static $instance;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Get translators information.
     *
     * @param $lang Can be either 'all' for all availables languages, or one specific language
     * @return An associated array
     */
    public function getTranslators($lang='all')
    {
        if( $lang == 'all' ) {
            $where = '';
        } else {
            $where = 'WHERE `lang`="'.$lang.'"';
        }

        $s =  'SELECT 
                 `id`, `nick`, `name`, `mail`, `vcs`, `lang`
               FROM
                 `translators`
               '.$where.'
        ';

        $result = DBConnection::getInstance()->query($s);

        $persons = array();

        while ($r = $result->fetch_object()) {
            $persons[$r->lang][$r->nick] = array(
                'id'   => $r->id,
                'name' => utf8_encode($r->name),
                'mail' => $r->mail,
                'vcs'  => $r->vcs
            );
        }
        return $persons;
    }

    /**
     * Get number of uptodate files per translators.
     *
     * @param $lang Can be either 'all' for all availables languages, or one specific language
     * @return An associated array
     */
    public function getUptodateFileCount($lang='all')
    {
        if( $lang == 'all' ) {
            $where = '';
        } else {
            $where = '`lang`="'.$lang.'" AND';
        }

        $s = 'SELECT
                COUNT(`name`) AS total,
                `maintainer`,
                `lang`
            FROM
                `files`
            WHERE
                ' . $where . '
                `revision` = `en_revision`
            GROUP BY
                `maintainer`
            ORDER BY
                `maintainer`
        ';
        $r = DBConnection::getInstance()->query($s);

        $result = array();
        while ($a = $r->fetch_object()) {
            $result[$a->lang][$a->maintainer] = $a->total;
        }
        return $result;
    }

    /**
     * Get number of old files per translators.
     *
     * @param $lang Can be either 'all' for all availables languages, or one specific language
     * @return An associated array
     */
    public function getStaleFileCount($lang='all')
    {
        if( $lang == 'all' ) {
            $where = '';
        } else {
            $where = '`lang`="'.$lang.'" AND';
        }

        $s = 'SELECT
                COUNT(`name`) AS total,
                `maintainer`,
                `lang`
            FROM
                `files`
            WHERE
                ' . $where . '
                `en_revision` != `revision`
            AND
                `size` is not NULL
            GROUP BY
                `maintainer`
            ORDER BY
                `maintainer`
        ';
        $r = DBConnection::getInstance()->query($s);

        $result = array();
        while ($a = $r->fetch_object()) {
            $result[$a->lang][$a->maintainer] = $a->total;
        }
        return $result;
    }

    /**
     * Compute statistics summary about translators and store it into DB
     *
     * @param $lang Can be either 'all' for all availables languages, or one specific language
     */
    public function computeSummary($lang='all')
    {

        $translators = $this->getTranslators($lang);
        $uptodate    = $this->getUptodateFileCount($lang);
        $stale       = $this->getStaleFileCount($lang);

        if( $lang == 'all' ) {
            $hereLang = RepositoryManager::getInstance()->availableLang;
        } else {
            $hereLang = array(0 => Array("code" => $lang));
        }

        foreach( $hereLang as $lang ) {

            $lang = $lang["code"];

            $i=0; $persons=array();
            foreach ($translators[$lang] as $nick => $data) {
                $persons[$i]              = $data;
                $persons[$i]['nick']      = $nick;
                $persons[$i]['uptodate']  = isset($uptodate[$lang][$nick]) ? $uptodate[$lang][$nick] : '0';
                $persons[$i]['stale']     = isset($stale[$lang][$nick])      ? $stale[$lang][$nick]      : '0';
                $persons[$i]['sum']       = $persons[$i]['uptodate'] + $persons[$i]['stale'];
                $i++;
            }

            // Save $summary into DB
            RepositoryManager::getInstance()->setStaticValue('translator_summary', $lang, json_encode($persons));
        }
    }
}

?>
