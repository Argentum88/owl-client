<?php

namespace Client\Library {

    use Tracy\Debugger as TracyDebugger;

    /**
     * Class Debugger
     *
     * @package Core
     */
    class Debugger extends TracyDebugger
    {

        /**
         * Вывод содержимого переменной
         *
         * @param      $var
         * @param bool $return
         * @param bool $exit
         *
         * @return mixed|void
         */
        public static function dump($var, $return = false, $exit = true)
        {
            parent::$maxDepth = 15;

            (ob_get_contents() || ob_get_length()) ? ob_clean() : null;
            parent::dump($var, $return);

            !$exit ?: exit;
        }

        /**
         * Вывод содержимого переменной в отладочную панель
         *
         * @param       $var
         * @param null $title
         * @param array $options
         *
         * @return mixed|void
         */
        public static function dumpBar($var, $title = null, array $options = null)
        {
            parent::$maxDepth = 15;
            parent::barDump($var, $title, $options);
        }
    }
}
