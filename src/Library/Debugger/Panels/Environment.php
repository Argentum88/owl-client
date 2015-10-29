<?php

namespace Client\Library\Debugger\Panels {

    use Tracy\IBarPanel;
    use Phalcon\Mvc\User\Component;

    /**
     * Выводит название текущего окружения
     *
     */
    class Environment extends Component implements IBarPanel
    {

        /**
         * Renders HTML code for custom tab.
         *
         * @return string
         */
        public function getTab()
        {
            return PH_DEBUG ? 'DEV' : 'PROD';
        }

        /**
         * Renders HTML code for custom panel.
         *
         * @return string
         */
        public function getPanel()
        {
            return false;
        }
    }
}
