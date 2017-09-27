<?php

    /**
     * 管理费自动结算
     * User: 芶凌
     * Date: 2017/7/27
     * Time: 15:54
     */
    class overheads {
        public function __construct() {
            bcscale(2);
        }

        /**
         * 启动多线程分帐写数据
         */
        public function setProrate() {
            $list = $this->__getProrate();
            print_r($list);
        }

        /**
         * 本地取多个用户回款信息
         * 第三方取用户活期定期信息
         * 计算用户收益、平台收益、结算日期、每笔回款扣取管理费信息
         */
        private function __getProrate() {
            $list = $this->__getUserList();
            $date = date_create('2017-08-09');

            foreach ($list as $k => &$user) {
                $client = $this->__getUserData($user);
                if ($user['date'] === false) {
                    $user['date'] = $client['date'];
                }

                $user['backData'] = $this->__getUserPlatformCash($client, $user, $date);
                $user['diffInterest'] = 0;

                if ($user['backData']['platform'] > 0) {
                    $this->__setPlatformCash($user);
                }
                ksort($user);
            }

            return $list;
        }

        /**
         * 按回款记录扣取管理费
         * @param $user
         */
        private function __setPlatformCash(&$user) {
            $alreadyTakePlatform = 0;
            $lastBackYesterday = array_pop($user['backList']);

            $user['backData']['proportion'] = $this->__getFloorDigits($user['backData']['platform'] / $user['backCash'], 8); //不除以回款金额修改为当前给出的债权回款利息总值且回款利息需要大于0
            foreach ($user['backList'] as $k => &$v) {
                $alreadyTakePlatform += $v['data']['cash'] = $this->__getFloorDigits($v['cash'] * $user['backData']['proportion']);
                $v['data']['proportion'] = $user['backData']['proportion'];
            }

            $lastBackYesterday['data']['cash'] = bcsub($user['backData']['platform'], $alreadyTakePlatform);
            $lastBackYesterday['data']['proportion'] = $this->__getFloorDigits($lastBackYesterday['data']['cash'] / $lastBackYesterday['cash'], 8);

            array_push($user['backList'], $lastBackYesterday);
        }

        /**
         * 计算用户与平台各自的收益
         * @param $client
         * @param $user
         * @param $date
         * @return array
         */
        private function __getUserPlatformCash($client, &$user, $date) {
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

            if ($user['userPrepareCash'] <= 0) {
                /**
                 * 用户预期收益<=0，本次结算用户需要偿还欠平台先前垫付的利息且依然欠平台的钱
                 * 此条件由于推算时间已到最后结算时间点导致用户无收益，如未推算时间则用户应收收益不可能为0
                 * 本次回款全交由用户，平台无需扣取管理费
                 */
                if ($user['userWantCash'] == 0) {
                    $data['user'] = $user['backCash'];
                    $data['platform'] = 0;
                } else {
                    /**
                     * 确保用户每次回款必收到利息，平台优先为用户垫付1分钱
                     * 用户收到的利息为平台垫付1分钱，又因已抵消了原用户欠平台的钱因此用户欠平台利息变更为用户预期收益(抵消后依然欠平台的钱)+平台本次垫付的1分钱
                     * 平台由于为用户垫付1分钱，因此收到的管理费为本次结算回款利息减去垫付给用户的1分钱
                     * 这种情况下，平台已经完全偿还了欠用户的钱，因此清空平台欠用户的钱为0
                     */
                    $data['user'] = 0.01;
                    $data['platform'] = bcsub($user['backCash'], $data['user']);
                }
                $user['diffPlatform'] = bcsub($user['userPrepareCash'], $data['user']);
                $user['diffUser'] = 0;
            } else if ($user['userPrepareCash'] <= $user['backCash']) {
                /**
                 * 用户预期有收益，并且用户昨天回款>=用户预期收益
                 * 表示用户欠平台与平台欠用户的利息在本次结算中已相互抵消并且用户还能收到回款中的利息
                 * 用户收到的利息=用户预期收益
                 * 平台收到的管理费=用户回款利息-用户收到的利息
                 * 由于已抵消用户欠平台与平台欠用户的利息因此清空此两值
                 */
                $data['user'] = $user['userPrepareCash'];
                $data['platform'] = bcsub($user['backCash'], $user['userPrepareCash']);
                $user['diffPlatform'] = $user['diffUser'] = 0;
            } else if ($user['userDiffCash'] >= $user['backCash']) {
                /**
                 * 用户欠平台计负，平台欠用户计正，平台欠用户(正)+用户欠平台(负) >= 昨日回款，只可能平台欠用户才可能大于昨日回款收益
                 * 抵消后(差异值)用户不可能欠平台钱，回款收益要全部给用户以抵消平台欠用户的钱
                 * 计算平台还差用户多少钱＝差异值－用户回款收益
                 */
                $data = array(
                    'user' => $user['backCash'],
                    'platform' => 0,
                    'date' => $user['date']
                );
                $user['diffUser'] = bcsub($user['userDiffCash'], $user['backCash']);
                $user['diffPlatform'] = 0;
            } else {
                /**
                 * 抵消后用户依然欠平台的钱，且不足以偿还平台垫付的钱
                 * 需要找一个结算时间点让其足以偿还平台垫付的钱
                 */
                $date = $this->__getDate($client, $user, $date);
                return $this->__getUserPlatformCash($client, $user, $date);
            }

            return $data;
        }

        /**
         * 推算合理的结算日期
         * @param $client
         * @param $user
         * @param $date
         * @return static
         */
        private function __getDate($client, $user, $date) {
            $bigDate = $date;
            $smallDate = date_create($user['date']->format('Y-m-d'));
            $refNowDate = $bigDate;
            $upUserWantCash = $user['userWantCash'];
            $index = 10;

            while ($index > 0) {
                $diffDay = (int)$this->__getFloorDigits(date_diff($smallDate, $bigDate)->format('%R%a') / 2);
                $refNowDate = date_create($smallDate->format('Y-m-d'))->add(date_interval_create_from_date_string("{$diffDay} days"));

                $live = $this->__getLiveSum($client, $user['date'], $refNowDate);
                $fixed = $this->__getFixedSum($client, $user['date'], $refNowDate);

                $user['userWantCash'] = $live['sum'] + $fixed['sum'];
                $user['userPrepareCash'] = $user['userWantCash'] + $user['userDiffCash'];
                if ($user['userPrepareCash'] <= $user['backCash']) {
                    $smallDate = $refNowDate;
                } else {
                    $bigDate = $refNowDate;
                }

                if ($upUserWantCash == $user['userWantCash']) {
                    break;
                } else {
                    $upUserWantCash = $user['userWantCash'];
                }

                $index--;
            }

            return $refNowDate;
        }

        /**
         * 获取有效回款用户列表
         */
        private function __getUserList() {
            /**
             * 昨日回款利息+违约金
             * 最后结算时间
             * 累加承接利息
             * 用户欠平台的钱(小于等于0的值)
             * 平台欠用户的钱(大于等于0的数)
             */
            return array(
                array(
                    'userId' => 1,
                    'platformId' => 1,
                    'backCash' => 5,
                    'date' => date_create('2017-07-25'),
                    'diffInterest' => -6,
                    'diffPlatform' => 0,
                    'diffUser' => 0,
                    'backList' => array(
                        array(
                            'id' => 1,
                            'cash' => 2,
                        ),
                        array(
                            'id' => 1,
                            'cash' => 3,
                        )
                    )
                ),
            );
        }

        /**
         * 获取业务端参数
         * @param $user array(platformId, userId, date)
         * @return array
         */
        private function __getUserData($user) {
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
                        'date' => date_create('2017-07-25'),
                        'cash' => 5
                    ),
                    array(
                        'date' => date_create('2017-07-26'),
                        'cash' => 20
                    ),
                    array(
                        'date' => date_create('2017-07-27'),
                        'cash' => 6
                    ),
                    array(
                        'date' => date_create('2017-07-28'),
                        'cash' => 5
                    ),
                    array(
                        'date' => date_create('2017-07-29'),
                        'cash' => 16
                    ),
                    array(
                        'date' => date_create('2017-07-30'),
                        'cash' => 6
                    ),
                    array(
                        'date' => date_create('2017-07-31'),
                        'cash' => 5
                    ),
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
                 */
                'fixed' => array(
                    '1001' => array(
                        'cash' => 1000,
                        'fixed_rate' => 0.12,
                        'lives_rate' => 0.12,
                        'date' => date_create('2017-07-25'),
                    ),
                    '1002' => array(
                        'cash' => 500,
                        'fixed_rate' => 0.10,
                        'lives_rate' => 0.10,
                        'date' => date_create('2017-07-26'),
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

    $tool = new overheads();
    $tool->setProrate();
