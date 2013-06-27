<?php

    /**
     * City collection
     */
    class city_collection extends record_collection implements city_definition {

        /**
         * Preload the Region models in this collection
         *
         * @return region_collection
         */
        public function region_collection() {
            return $this->_preload_one_to_one('region', 'region_id');
        }
    }
