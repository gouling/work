<?php
    class CCache {
        private $__handle, $__prefix;
        
        public function __construct($handle, $prefix) {
            $this->__handle = $handle;
            $this->__prefix = $prefix;
        }
        
        public function getPageData($page) {
            return $this->__handle->getPageData($this->__prefix, $page);
        }
        
        public function setPageData($page, $data) {
            return  $this->__handle->setPageData($this->__prefix, $page, $data);
        }
        
        public function finalize() {
            $this->__handle->del($this->__prefix);
        }
    }
