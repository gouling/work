<?php

    /**
     * 提现管理费
     * User: 芶凌
     * Date: 2017/7/27
     * Time: 15:54
     */
    class withdraw {
        public function __construct() {
            bcscale(2);
        }

        public function get($user) {
            $date = date_create('2017-08-09');
            $client = $this->__getClient($user);

            if ($user['date'] === false) {
                $user['date'] = $client['date'];
            }

            /**
             * ０＝活期提现
             * １＝定期到期退出提现
             * ２＝定期提前退出提现
             */
            switch ($client['type']) {
                case 0:
                    $user['backData'] = $this->__getDefault($user, $client, $date);
                    break;
                case 1:
                    $user['backData'] = $this->__getFixed($user, $client, $date);
                    break;
                case 2:
                    $user['backData'] = $this->__getLives($user, $client, $date);
                    break;
                default:
                    throw new \Exception('提现类型暂未支持手续费的扣取。', 412);
            }

            $user['backData']['red'] = $user['diffInterest'] = $user['diffPlatform'] = $user['diffUser'] = 0;
            if ($user['backData']['platform'] < 0) { //如果平台收到的管理费为负数则表示平台欠用户平台收到的负数管理员
                $user['backData']['red'] = -$user['backData']['platform'];
                $user['backData']['platform'] = 0;
            }
            unset($user['backData']['user']);
            /*if ($user['backData']['user'] < 0) {
                $user['backData']['user'] = 0;
            }*/
            print_r($user['backData']);
        }

        /**
         * 活期提现
         * @param $user
         * @param $client
         * @param $date
         * @return array
         * @throws Exception
         */
        private function __getDefault(&$user, $client, $date) {
            $data = array(
                'user' => 0,
                'platform' => 0,
                'date' => $date
            );

            /**
             * 用户本次结算理论上应收收益=活期收益+定期收益
             * 用户本次结算差额收益＝承接利息+用户欠平台的钱(小于等于0的值)+平台欠用户的钱(大于等于0的值)
             * 用户预期收益＝用户本次结算理论上应收收益+用户本次结算差额收益
             */
            $live = $this->__getLiveSum($client, $user['date'], $date);
            $fixed = $this->__getFixedSum($client, $user['date'], $date);

            $user = array_merge($user, array(
                'userDiffCash' => bcadd(bcadd($user['diffInterest'], $user['diffPlatform']), $user['diffUser']),
                'userWantCash' => $live['sum'] + $fixed['sum'],
                'userPrepareCash' => bcadd(bcadd(bcadd(bcadd($user['diffInterest'], $user['diffPlatform']), $user['diffUser']), $live['sum']), $fixed['sum']),
                'live' => $live,
                'fixed' => $fixed,
            ));

            $data['platform'] = bcsub($user['backCash'], $user['userPrepareCash']);
            if ($user['userPrepareCash'] >= 0) {
                $data['user'] = $user['userPrepareCash'] <= $user['backCash'] ? $user['userPrepareCash'] : $user['backCash'];
            }

            return $data;
        }

        /**
         * 定期提前退出
         * @param $user
         * @param $client
         * @param $date
         * @return array
         * @throws Exception
         */
        private function __getLives(&$user, $client, $date) {
            if (!isset($client['fixedId']) || !isset($client['fixed'][$client['fixedId']])) { //设置了定期提前退出却未设定定期参数
                throw new \Exception('设置了定期提前退出却未设定定期参数。', 412);
            }

            $data = array(
                'user' => 0,
                'platform' => 0,
                'date' => $date
            );

            /**
             * 用户本次结算理论上应收收益=活期收益+定期收益
             * 用户本次结算差额收益＝承接利息+用户欠平台的钱(小于等于0的值)+平台欠用户的钱(大于等于0的值)
             * 用户预期收益＝用户本次结算理论上应收收益+用户本次结算差额收益
             */
            $current = $client['fixed'][$client['fixedId']];
            unset($client['fixed'][$client['fixedId']]);

            $live = $this->__getLiveSum($client, $user['date'], $date);
            $fixed = $this->__getFixedSum($client, $user['date'], $date);

            /**
             * 最近一次结算时间小于等于提现笔充值时间
             */
            $current['day'] = date_diff($current['date'], $date)->format('%R%a');
            if (date_diff($user['date'], $current['date'])->format('%R%a') >= 0) {
                $current['fixExpEarnings'] = $this->__getFloorDigits($current['day'] * ($current['cash'] * $current['lives_rate'] / 365));
                $current['diffFixExpEarnings'] = 0;
            } else {
                /**
                 * 定期开始到提现日的收益
                 * 定期开始到用户上次结算的收益
                 */
                $current['diffInterest'] = array(
                    'SN' => $this->__getFloorDigits($current['day'] * ($current['cash'] * $current['lives_rate'] / 365)),
                    'SD' => $this->__getFloorDigits(date_diff($current['date'], $user['date'])->format('%R%a') * ($current['cash'] * $current['lives_rate'] / 365)),
                );
                $current['fixExpEarnings'] = $current['diffInterest']['SN'] - $current['diffInterest']['SD'];
                $current['diffFixExpEarnings'] = $current['fixExpEarningsSum'] - $current['diffInterest']['SD'];
            }

            $fixed['sum'] += $current['fixExpEarnings'];
            $fixed['data'][$client['fixedId']] = $current;
            $client['fixed'][$client['fixedId']] = $current;

            $user['diffPlatform'] -= $current['diffFixExpEarnings'];
            $user = array_merge($user, array(
                'userDiffCash' => bcadd(bcadd($user['diffInterest'], $user['diffPlatform']), $user['diffUser']),
                'userWantCash' => $live['sum'] + $fixed['sum'],
                'userPrepareCash' => bcadd(bcadd(bcadd(bcadd($user['diffInterest'], $user['diffPlatform']), $user['diffUser']), $live['sum']), $fixed['sum']),
                'live' => $live,
                'fixed' => $fixed,
            ));

            $data['platform'] = bcsub($user['backCash'], $user['userPrepareCash']);
            if ($user['userPrepareCash'] >= 0) {
                $data['user'] = $user['userPrepareCash'] <= $user['backCash'] ? $user['userPrepareCash'] : $user['backCash'];
            }

            return $data;
        }

        /**
         * 定期到期退出
         * @param $user
         * @param $client
         * @param $date
         * @return array
         * @throws Exception
         */
        private function __getFixed(&$user, $client, $date) {
            if (!isset($client['fixedId']) || !isset($client['fixed'][$client['fixedId']])) { //设置了定期提前退出却未设定定期参数
                throw new \Exception('设置了定期到期退出却未设定定期参数。', 412);
            }

            $data = array(
                'user' => 0,
                'platform' => 0,
                'date' => $date
            );

            /**
             * 移除提现定期以方便调用共用函数计算其它定期的收益
             */
            $current = $client['fixed'][$client['fixedId']];
            unset($client['fixed'][$client['fixedId']]);

            $live = $this->__getLiveSum($client, $user['date'], $date);
            $fixed = $this->__getFixedSum($client, $user['date'], $date);

            /**
             * 提现指定定期收益计算
             *
             * 天数
             * 收益=定期开始到提现日的收益 - 已结算收益
             * 重写累计定期收益
             * 定期回写入定期列表
             */
            $current['day'] = date_diff($current['date'], $date)->format('%R%a');
            $current['fixExpEarnings'] = bcsub($this->__getFloorDigits($current['day'] * $current['cash'] * $current['fixed_rate'] / 365), $current['fixExpEarningsSum']);
            $fixed['sum'] += $current['fixExpEarnings'];
            $fixed['data'][$client['fixedId']] = $current;

            /**
             * 用户本次结算理论上应收收益=活期收益+定期收益
             * 用户本次结算差额收益＝承接利息+用户欠平台的钱(小于等于0的值)+平台欠用户的钱(大于等于0的值)
             * 用户预期收益＝用户本次结算理论上应收收益+用户本次结算差额收益
             */
            $user = array_merge($user, array(
                'userDiffCash' => bcadd(bcadd($user['diffInterest'], $user['diffPlatform']), $user['diffUser']),
                'userWantCash' => $live['sum'] + $fixed['sum'],
                'userPrepareCash' => bcadd(bcadd(bcadd(bcadd($user['diffInterest'], $user['diffPlatform']), $user['diffUser']), $live['sum']), $fixed['sum']),
                'live' => $live,
                'fixed' => $fixed,
            ));

            $data['platform'] = bcsub($user['backCash'], $user['userPrepareCash']);
            if ($user['userPrepareCash'] >= 0) {
                $data['user'] = $user['userPrepareCash'] <= $user['backCash'] ? $user['userPrepareCash'] : $user['backCash'];
            }

            return $data;
        }

        /**
         * 获取业务端参数
         * @param $user array(platformId, userId, date)
         * @return array
         */
        private function __getClient($user) {
            $data = array(
                /**
                 * 跨平台标识规则(platform.user.YmdHis)
                 * 平台用户首笔充值日期 Y-m-d
                 * 活期收益 最后一次结算时间到到当前结算时间(不包含当前时间)活期收益列表（每天）
                 */
                'id' => "PlatformId：{$user['platformId']}，UserId：{$user['userId']}",
                'date' => date_create('2017-07-20'),
                'live' => array(
                    array(
                        'date' => date_create('2017-08-01'),
                        'cash' => 16
                    ),
                    array(
                        'date' => date_create('2017-08-02'),
                        'cash' => 6
                    ),
                    array(
                        'date' => date_create('2017-08-03'),
                        'cash' => 5
                    ),
                    array(
                        'date' => date_create('2017-08-04'),
                        'cash' => 16
                    ),
                    array(
                        'date' => date_create('2017-08-05'),
                        'cash' => 6
                    ),
                    array(
                        'date' => date_create('2017-08-06'),
                        'cash' => 5
                    ),
                    array(
                        'date' => date_create('2017-08-07'),
                        'cash' => 16
                    ),
                    array(
                        'date' => date_create('2017-08-08'),
                        'cash' => 6
                    ),
                ),
                /*
                 * 本金
                 * 到期退出年收益率
                 * 提前退出年收益率
                 * 充值时间
                 * 已结算
                 */
                'type' => 2,
                //０＝活期提现，１＝定期到期退出提现，２＝定期提前退出提现
                'fixedId' => 1001,
                'fixed' => array(
                    '1001' => array(
                        'cash' => 1000,
                        'fixed_rate' => 0.11,
                        'lives_rate' => 0.10,
                        'date' => date_create('2017-07-25'),
                        'fixExpEarningsSum' => 74
                    ),
                    '1002' => array(
                        'cash' => 500,
                        'fixed_rate' => 0.12,
                        'lives_rate' => 0.11,
                        'date' => date_create('2017-07-26'),
                        'fixExpEarningsSum' => 100
                    ),
                ),
            );

            return $data;
        }

        /**
         * 获取指定时间段活期收益
         * @param $data
         * @param $dateBegin
         * @param $dateEnd
         * @return array
         */
        private function __getLiveSum($data, $dateBegin, $dateEnd) {
            $dateBegin = $dateBegin;
            $dateEnd = $dateEnd;
            $liveSum = 0;

            foreach ($data['live'] as $k => $v) {
                if (date_diff($dateBegin, $v['date'])->format('%R%a') >= 0 && date_diff($dateEnd, $v['date'])->format('%R%a') < 0) {
                    $liveSum += $v['cash'];
                } else {
                    unset($data['live'][$k]);
                }
            }

            return array(
                'data' => $data['live'],
                'sum' => $liveSum
            );
        }

        /**
         * 获取指定时间段定期收益
         * @param $data
         * @param $dateBegin Y-m-d 最后结算时间
         * @param $dateEnd Y-m-d 当前结算时间
         * @return array
         */
        private function __getFixedSum($data, $dateBegin, $dateEnd) {
            $fixedSum = 0;

            foreach ($data['fixed'] as $k => &$v) {
                if (date_diff($dateBegin, $v['date'])->format('%R%a') >= 0) {
                    $dateBegin = $v['date'];
                }
                $day = date_diff($dateBegin, $dateEnd)->format('%R%a');
                $v['day'] = $day > 0 ? $day : 0;
                $fixedSum += $v['fixExpEarnings'] = $this->__getFloorDigits($v['day'] * ($v['cash'] * $v['fixed_rate'] / 365));
            }

            return array(
                'data' => $data['fixed'],
                'sum' => $fixedSum
            );
        }

        /**
         * 向下保留小数位
         * @param $data
         * @return float|int
         */
        private function __getFloorDigits($data, $digit = 2) {
            $data = number_format($data, 8, '.', '');
            preg_match('/^[-\d]\d*\.\d{' . $digit . '}/i', $data, $refer);

            return doubleval($refer[0]);
        }

        public function __destruct() {
        }
    }

    /**
     * 昨日回款利息+违约金+当日当前日期回款利息
     * 最后结算时间
     * 累加承接利息
     * 用户欠平台的钱(小于等于0的值)
     * 平台欠用户的钱(大于等于0的数)
     */
    $user = array(
        'userId' => 1,
        'platformId' => 1,
        'backCash' => -72.11,
        'date' => date_create('2017-08-01'),
        'diffInterest' => 0,
        'diffPlatform' => -79.51,
        'diffUser' => 0
    );

    $tool = new withdraw();
    $tool->get($user);
