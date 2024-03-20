<?php

namespace MediaWiki\Extension\MinimalExample;

use ApiBase;

class ApiDoesUserExist extends ApiBase {

    public function execute() {
        $this->getResult()->addValue(
            null,
            $this->getModuleName(),
            [ 'implemented' => 'not yet' ]
        );
    }

}
