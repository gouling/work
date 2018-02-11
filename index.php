<?php
    class CZookeeper {
        private $__zookeeper;

        public function getConfigByName($name) {
            $data = array();

            if ($this->__zookeeper->exists("/{$name}")) {
                $child = $this->__zookeeper->getchildren("/{$name}");
                foreach ($child as $node) {
                    $data[$node] = $this->__zookeeper->get("/{$name}/{$node}");
                }
                $data['doc'] = $this->__zookeeper->get("/{$name}");
            }

            return $data;
        }

        public function __construct($zkConnInfo) {
            $this->__zookeeper = new \Zookeeper($zkConnInfo);
        }
    }

    $zookeeper = new CZookeeper('127.0.0.1:2181');
    $beauty = $zookeeper->getConfigByName('beauty');
    print_r($beauty);

    class CSolr {
        private $__solr, $__query;

        public function query($query = '*:*', $offset = 0, $pageSize = 10) {
            $this->__query->setQuery($query);
            $this->__query->setStart($offset);
            $this->__query->setRows($pageSize);

            return $this->__solr->query($this->__query)->getResponse();
        }

        public function __construct($solrConnInfo) {
            $this->__solr = new \SolrClient($solrConnInfo);
            $this->__solr->setResponseWriter('json');

            $this->__query = new \SolrQuery();
        }
    }

    $solr = new CSolr(array(
        'hostname' => '127.0.0.1',
        'path' => '/solr/tender',
        'port' => '8983',
    ));

    $pageSize = 10;
    $page = isset($_GET['page']) ? $_GET['page'] : 1;
    $page = ($page - 1) * $pageSize;
    print_r($solr->query('id:1 OR borrow_name:*女士*', $page, $pageSize));