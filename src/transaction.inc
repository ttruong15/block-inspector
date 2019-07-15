<?php
//////////////////////////////////////////////////////////////////////////////
// Copyright (C) 2019 - Tinh Truong                                         //
//									                                        //
// Date:		24/06/2019													//
// Created by:	Tinh Truong @edna.life                                      //
//                                                                          //
// This source code is subject to commerical confidentiality and may not be //
// disclosed to unauthorised thrid parties without written permission from  //
// the owner.																//
//////////////////////////////////////////////////////////////////////////////
require_once("eosio_chain.inc");

class Transaction {

	private $transactions = array();
	private $eosio = null;

	public function __construct(EosioChain $eosio) {
		$this->eosio = $eosio;
	}

	public function getTransactions() {
		return $this->transactions;
	}

	/**
	 * get transactions for a blocknum
	 *
	 * @param integer $blockNum
	 * @return array
	 */
    public function processBlock($blockNum) {
		$block = $this->eosio->getBlock($blockNum);
		$this->transactions = $block['transactions'];

		return $this;
    }

	public function getExecutedTransaction() {
		
	}
}
