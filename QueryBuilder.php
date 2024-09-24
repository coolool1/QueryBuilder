<?php

namespace FpDbTest;

use Exception;
use FpDbTest\Database\skip;

class QueryBuilder
{
    private $blocks;
    private $quotes;
    private $query;
    private $lengthQuery;
    private $args;
    private $indexArg;
    private $lengthArgs;

    public function __construct() {
        $this->blocks = [];
        $this->quotes = [];
        $this->args = [];
        $this->indexArg = 0;
        $this->lengthArgs = 0;
        $this->query = "";
        $this->lengthQuery = 0;
    }

    private function arrayToQuery($arrayData) : string {
        if(!is_array($arrayData)) throw New Exception("Not correct type for replacement value to query");
        $chunks = [];
        $isSetting = array_keys($arrayData) !== range(0, count($arrayData) - 1);
        if($isSetting) {
          foreach ($arrayData as $key => $value) {
            if(!is_string($key)) throw New Exception("Not correct type of key - array element");
            $valueToQuery = $this->noSpecSymbolToQuery($value);
            $chunks[] = "`$key` = " .$valueToQuery;
          }
        } else {
          foreach ($arrayData as $key => $value) {
            $valueToQuery = $this->noSpecSymbolToQuery($value);
            $chunks[] = $valueToQuery;
          }
        };
        return implode(', ', $chunks);
      }
  
      private function identifierToQuery($ident) : string {
        if(is_array($ident)) {
          $chunks = [];
          foreach ($ident as $item) {
              if(!is_string($item)) throw New Exception("Not correct type of Identifier - array element {$item}");
              $chunks[] = "`$item`";
          }
          return implode(", ", $chunks);
        } elseif (is_string($ident)) {
          return "`$ident`";
        } else throw New Exception("Not correct type for replacement value to query");
      }
  
      private function setConditionalBlocks()
      {
        $indexOpeningBlock = 0;
        $indexClosingBlock = 0;
        $tumblerOpening = false;
        $c = '';
        for ($i = 0; $i < $this->lengthQuery; $i++) {
          $c = $this->query[$i];
          if($c == '{' && !$this->isInQuotes($i)) {
            if($tumblerOpening) throw new Exception("Not correct query, wrong conditional block");
            $indexOpeningBlock = $i;
            $tumblerOpening = true;
          }
          if($c == '}' && !$this->isInQuotes($i)) {
            if(!$tumblerOpening) throw new Exception("Not correct query, wrong conditional block");
            $indexClosingBlock = $i;
            $this->blocks[] = ['oldIndexOpeningBlock'=>$indexOpeningBlock,'oldIndexClosingBlock'=>$indexClosingBlock,
                               'indexOpeningBlock'=>$indexOpeningBlock,'indexClosingBlock'=>$indexClosingBlock,'flagDelete'=>false];
            $tumblerOpening = false;
          }
        };
      }

      private function isInQuotes($index):bool {
        if(empty($this->quotes)) return false;
        foreach($this->quotes as $value)
          if($value['indexOpeningQuote'] < $index && $index < $value['indexClosingQuote']) 
            return true;
        return false;  
      }

      private function checkAndSetOneOrTwoQuote($i,&$indexOpeningQuote,&$indexClosingQuote,&$tumbler,&$isQuoteMySQL,&$numbersQuoters) {
        $isShielded = ($i>0) ? $this->query[$i-1] === '\\' : false;
          if($tumbler === true) {
            if($isShielded) return;
            $indexClosingQuote = $i;
            if($isQuoteMySQL){
              $last = count($this->quotes) - 1;
              $this->quotes[$last]['oldIndexClosingQuote'] = $indexClosingQuote;
              $this->quotes[$last]['indexClosingQuote'] = $indexClosingQuote;
              $isQuoteMySQL = false;
            } else {
              $this->quotes[] = ['oldIndexOpeningQuote'=>$indexOpeningQuote,'oldIndexClosingQuote'=>$indexClosingQuote,
              'indexOpeningQuote'=>$indexOpeningQuote,'indexClosingQuote'=>$indexClosingQuote]; 
            }
          } else {
            if($isShielded) throw new Exception("Not Correct query, wrong quotes in {$i}");
            if($i-1 == $indexClosingQuote) {
              $isQuoteMySQL = true;
            } else $indexOpeningQuote = $i;
          }
          $numbersQuoters++;
          $tumbler = !$tumbler;
      }

      private function lastCheckOneOrTwoQuote($indexOpeningQuote,$indexClosingQuote,$isQuoteMySQL,$numbersQuoters) {
        if($numbersQuoters % 2 !== 0) throw new Exception("ERROR QUOTES");
        if($indexClosingQuote<$indexOpeningQuote) {
          if($isQuoteMySQL) {
            $last =  count($this->quotes) - 1;
            $this->quotes[$last]['indexClosingQuote'] = $indexOpeningQuote;
            $this->quotes[$last]['oldIndexClosingQuote'] = $indexOpeningQuote;
          } else throw new Exception("ERROR QUOTES");
        }
      }

      private function setQuotes() {
        if(empty($this->query)) return;
        $c = '';
        $tumblerOneQuote = false;
        $tumblerTwoQuote = false;
        $indexOpeningOneQuote = 0;
        $indexClosingOneQuote = 0;
        $indexOpeningTwoQuote = 0;
        $indexClosingTwoQuote = 0;
        $numbersOneQuoters = 0;
        $numberTwoQuoters = 0;
        $isOneQuoteMySQL = false;
        $isTwoQuoteMySQL = false;
        for($i=0;$i<$this->lengthQuery;$i++) {
          $c = $this->query[$i];
          if($c === "'" && !$tumblerTwoQuote) 
            $this->checkAndSetOneOrTwoQuote($i,$indexOpeningOneQuote,$indexClosingOneQuote,$tumblerOneQuote,$isOneQuoteMySQL,$numbersOneQuoters);
          if($c === '"' && !$tumblerOneQuote) 
            $this->checkAndSetOneOrTwoQuote($i,$indexOpeningTwoQuote,$indexClosingTwoQuote,$tumblerTwoQuote,$isTwoQuoteMySQL,$numberTwoQuoters);
        }
        $this->lastCheckOneOrTwoQuote($indexOpeningOneQuote,$indexClosingOneQuote,$isOneQuoteMySQL,$numbersOneQuoters);
        $this->lastCheckOneOrTwoQuote($indexOpeningTwoQuote,$indexClosingTwoQuote,$isTwoQuoteMySQL,$numberTwoQuoters);
      }
  
      private function getIndexConditionalBlock($matchIndex) :int | bool {
        if(empty($this->blocks)) return false;
        foreach($this->blocks as $key =>$value) {
          if($matchIndex > $value['oldIndexOpeningBlock'] && $matchIndex < $value['oldIndexClosingBlock']) return $key;
        }
        return false;
      }
  
      private function noSpecSymbolToQuery($curArg) {
        $type = gettype($curArg);
        return match($type) {
          'string'=> "'".$curArg."'",
          'integer'=> $curArg,
          'double'=>$curArg,
          'boolean'=>(int) $curArg,
          'NULL'=>'NULL',
          default => throw New Exception("Not correct type for replacement value to query")
        };
      }
  
      private function digitalToQuery($curArg) {
        $type = gettype($curArg);
        return match($type) {
          'integer'=> $curArg,
          'boolean'=>(int) $curArg,
          'NULL'=>'NULL',
          default => throw New Exception("Not correct type for replacement value to query")
        };
      }
  
      private function floatToQuery($curArg) {
        $type = gettype($curArg);
        return match($type) {
          'integer'=> (float) $curArg,
          'double'=>$curArg,
          'boolean'=>(int) $curArg,
          'NULL'=>'NULL',
          default => throw New Exception("Not correct type for replacement value to query")
        };
      }
  
      private function checkBlockDelAndSet($matchIndex,$curArg) :bool {
        if(empty($this->blocks)) return false;
        $indexBlock = $this->getIndexConditionalBlock($matchIndex);
        $isExistBlock = $indexBlock !== false;
        if(Database::skip() === $curArg) {
          if($isExistBlock) {
            $this->blocks[$indexBlock]['flagDelete'] = true;
            return true;
          } else throw new Exception('Not correct place for special replacement value');
        } elseif($isExistBlock && $this->blocks[$indexBlock]['flagDelete'] == true) return true;
        return false; 
      }
  
      private function deleteBlocksIfExist($replacedQuery) :string {
        if(empty($this->blocks)) return $replacedQuery;
        $result = $replacedQuery;
        $offset = 0;
        foreach($this->blocks as $value) {
          if($value['flagDelete']) {
            $length = $value['indexClosingBlock'] - $value['indexOpeningBlock'] + 1;
            $result = substr_replace($result, '', $value['indexOpeningBlock'] + $offset, $length);
            $offset -= $length;
          } else {
            $result = substr_replace($result, '', $value['indexOpeningBlock'] + $offset, 1);
            $offset--;
            $result = substr_replace($result, '', $value['indexClosingBlock'] + $offset, 1);
            $offset--; 
          }
        }
        return $result;
      }
  
      private function updateOffsetBlocks($offset,$matchIndex) {
        foreach($this->blocks as &$value) {
          if($matchIndex < $value['oldIndexOpeningBlock']) {
            $value['indexOpeningBlock'] += $offset;
            $value['indexClosingBlock'] += $offset;
          } elseif($matchIndex < $value['oldIndexClosingBlock']) {
            $value['indexClosingBlock'] += $offset;
          }
        }
      }
  
      public function buildQuery(string $query, array $args = []): string
      {
        $this->lengthQuery = strlen($query);
        $this->lengthArgs = count($args);
        if($this->lengthQuery === 0 || $this->lengthArgs === 0) return $query;
        $this->query = $query;
        $this->args = $args;
        $this->setConditionalBlocks();
        $this->setQuotes();
        $replacedQuery =  preg_replace_callback("/\?[dfa#]?/",function($match) { 
          $matchIndex = $match[0][1];
          $matchValue = $match[0][0];
          if($this->isInQuotes($matchIndex)) return $matchValue;
          $curArg = $this->args[$this->indexArg]; 
          $isDeleteBlock = $this->checkBlockDelAndSet($matchIndex,$curArg);
          if($isDeleteBlock) {
            return ($this->indexArg++ === $this->lengthArgs)? throw new Exception("Out of bounds of an array of args") : $matchValue;
          }
  
          $res =  match($matchValue) {
            '?' => $this->noSpecSymbolToQuery($curArg),
            '?d' => $this->digitalToQuery($curArg),
            '?f' => $this->floatToQuery($curArg),
            '?a' => $this->arrayToQuery($curArg),
            '?#' => $this->identifierToQuery($curArg),
            default => throw New Exception('Not correct matchValue')
          };

          $offset = strlen($res) - strlen($matchValue);
          if(!empty($this->blocks)) $this->updateOffsetBlocks($offset,$matchIndex);
          return ($this->indexArg++ === $this->lengthArgs)? throw new Exception("Out of bounce in array of args") : $res;
        },$query,flags: PREG_OFFSET_CAPTURE);
        $result = $this->deleteBlocksIfExist($replacedQuery);
        $this->blocks = [];
        $this->quotes = [];
        $this->lengthQuery = 0;
        $this->query = "";
        $this->args =[];
        $this->lengthArgs;
        $this->indexArg = 0;
        var_dump($result);
        return $result;
      }
}