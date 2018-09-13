#!/usr/bin/env /usr/share/php-5.3.29/bin/php
<?php
    /**
     * 创建可排序永不重复包含字母数字每6位以短横线分隔的25位类式订单号的编码 5b9a0b-756bcb-127764-7879
     */
    function getIdentity() {
        $uniqid = str_replace('.', '', uniqid('', true));
        $serial = rtrim(chunk_split($uniqid, 6, '-'), '-');

        return $serial;
    }

    class CDigits {
        private $__digits;
        public function __construct($digits = 2) {
            $this->__digits = $digits;
        }

        /**
         * 向上保留小数位
         * @param $number
         * @return float|int
         */
        public function getCeilDigits($number, $digits = false) {
            $digits = $this->__getDigits($digits);
            $digits = pow(10, $digits);
            $number = doubleval($number);
            
            return ceil($number * $digits) / $digits;
        }

        /**
         * 四舍五入
         * @param $number
         * @return float
         */
        public function getRoundDigits($number, $digits = false) {
            $digits = $this->__getDigits($digits);
            $number = doubleval($number);
            
            return round($number, $digits);
        }
            
        /**
         * 向下保留小数位
         * @param $number
         * @return float|int
         */
        public function getFloorDigits($number, $digits = false) {
            $digits = $this->__getDigits($digits);
            $number = doubleval($number);
            $index = stripos($number, '.');
            
            return $index === false ? $number : doubleval(substr($number, 0, $index + $digits + 1));
        }
        
        private function __getDigits($digits) {
            return $digits === false ? $this->__digits : intval($digits);
        }
    }
