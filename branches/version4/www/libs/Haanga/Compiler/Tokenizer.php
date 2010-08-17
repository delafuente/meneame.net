<?php
/*
  +---------------------------------------------------------------------------------+
  | Copyright (c) 2010 César Rodas and Menéame Comunicacions S.L.                   |
  +---------------------------------------------------------------------------------+
  | Redistribution and use in source and binary forms, with or without              |
  | modification, are permitted provided that the following conditions are met:     |
  | 1. Redistributions of source code must retain the above copyright               |
  |    notice, this list of conditions and the following disclaimer.                |
  |                                                                                 |
  | 2. Redistributions in binary form must reproduce the above copyright            |
  |    notice, this list of conditions and the following disclaimer in the          |
  |    documentation and/or other materials provided with the distribution.         |
  |                                                                                 |
  | 3. All advertising materials mentioning features or use of this software        |
  |    must display the following acknowledgement:                                  |
  |    This product includes software developed by César D. Rodas.                  |
  |                                                                                 |
  | 4. Neither the name of the César D. Rodas nor the                               |
  |    names of its contributors may be used to endorse or promote products         |
  |    derived from this software without specific prior written permission.        |
  |                                                                                 |
  | THIS SOFTWARE IS PROVIDED BY CÉSAR D. RODAS ''AS IS'' AND ANY                   |
  | EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED       |
  | WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE          |
  | DISCLAIMED. IN NO EVENT SHALL CÉSAR D. RODAS BE LIABLE FOR ANY                  |
  | DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES      |
  | (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;    |
  | LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND     |
  | ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT      |
  | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS   |
  | SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE                     |
  +---------------------------------------------------------------------------------+
  | Authors: César Rodas <crodas@php.net>                                           |
  +---------------------------------------------------------------------------------+
*/

class HG_Parser Extends Haanga_Compiler_Parser
{
    /* subclass to made easier references to constants */
}


class Haanga_Compiler_Tokenizer
{
    /* they are case sensitive */
    static $tags = array(
        'block'         => HG_Parser::T_BLOCK,
        'load'          => HG_Parser::T_LOAD,
        'for'           => HG_Parser::T_FOR,
        'empty'         => HG_Parser::T_EMPTY,
        'TRUE'          => HG_Parser::T_TRUE,
        'FALSE'         => HG_Parser::T_FALSE,
        'AND'           => HG_Parser::T_AND,
        'OR'            => HG_Parser::T_OR,
        'not'           => HG_Parser::T_NOT,
        'NOT'           => HG_Parser::T_NOT,
        'if'            => HG_Parser::T_IF,
        'else'          => HG_Parser::T_ELSE,
        'ifequal'       => HG_Parser::T_IFEQUAL,
        'ifnotequal'    => HG_Parser::T_IFNOTEQUAL,
        'ifchanged'     => HG_Parser::T_IFCHANGED,
        'spacefull'     => HG_Parser::T_SPACEFULL,
        'autoescape'    => HG_Parser::T_AUTOESCAPE,
        'filter'        => HG_Parser::T_FILTER,
        'in'            => HG_Parser::T_IN,
        'include'       => HG_Parser::T_INCLUDE,
        'as'            => HG_Parser::T_AS,
        'by'            => HG_Parser::T_BY,
        'extends'       => HG_Parser::T_EXTENDS,
        'regroup'       => HG_Parser::T_REGROUP,
        'with'          => HG_Parser::T_WITH,
        '_('            => HG_Parser::T_INTL,
    );

    /* common operations */
    static $operations = array(
        '&&'    => HG_Parser::T_AND,
        '=='    => HG_Parser::T_EQ,
        '==='   => HG_Parser::T_EQ,
        '->'    => HG_Parser::T_OBJ,
        '||'    => HG_Parser::T_OR,
        '['     => HG_Parser::T_BRACKETS_OPEN,
        ']'     => HG_Parser::T_BRACKETS_CLOSE,
        '-'     => HG_Parser::T_MINUS,
        '+'     => HG_Parser::T_PLUS,
        '*'     => HG_Parser::T_TIMES,
        '/'     => HG_Parser::T_DIV, 
        ':'     => HG_Parser::T_COLON, 
        '.'     => HG_Parser::T_DOT,
        '>='    => HG_Parser::T_GE,
        '>'     => HG_Parser::T_GT,
        '<='    => HG_Parser::T_LE,
        '<'     => HG_Parser::T_LT,
        '|'     => HG_Parser::T_PIPE,
        '!='    => HG_Parser::T_NE,
        '!'     => HG_Parser::T_NOT,
        '('     => HG_Parser::T_LPARENT,
        ')'     => HG_Parser::T_RPARENT,
        ','     => HG_Parser::T_COMMA,
        '%'     => HG_Parser::T_MOD,
    );

    static $open_tag     = "{%";
    static $end_tag      = "%}";
    static $open_comment = "{#";
    static $end_comment  = "#}";
    static $open_print   = "{{";
    static $end_print    = "}}";

    public $open_tags;
    public $value;
    public $token;
    public $status = self::IN_NONE;

    const IN_NONE    = 0;
    const IN_HTML    = 1;
    const IN_TAG     = 2;
    const IN_ECHO    = 3;
    const IN_COMMENT = 4;

    function __construct($data, $compiler, $file)
    {
        $this->data     = $data;
        $this->compiler = $compiler;
        $this->line     = 1;
        $this->N        = 0;
        $this->file     = $file;
        $this->length   = strlen($data);


        self::$operations = array_merge(array(
                self::$end_tag   => HG_Parser::T_CLOSE_TAG,
                self::$end_print => HG_Parser::T_PRINT_CLOSE,
            ), self::$operations);

        $this->open_tags = array(
            self::$open_tag     => HG_Parser::T_OPEN_TAG,
            self::$open_print   => HG_Parser::T_PRINT_OPEN,
            self::$open_comment => HG_Parser::T_COMMENT_OPEN,
        );
    }

    function yylex()
    {
        $this->token = NULL;

        if ($this->length == $this->N) {
            return FALSE;
        }

        if ($this->status == self::IN_NONE) {
            $i    = &$this->N;
            $data = substr($this->data, $i, 12);

            static $lencache = array();
            foreach ($this->open_tags as $value => $token) {
                if (!isset($lencache[$value])) {
                    $lencache[$value] = strlen($value);
                }
                $len = $lencache[$value];
                if (strncmp($data, $value, $len) == 0) {
                    $this->value  = $value;
                    $this->token  = $token;
                    $i += $len;
                    switch ($this->token) {
                    case HG_Parser::T_OPEN_TAG:
                        $this->status = self::IN_TAG;
                        break;
                    case HG_Parser::T_COMMENT_OPEN:
                        $this->status = self::IN_COMMENT;
                        break;
                    case HG_Parser::T_PRINT_OPEN:
                        $this->status = self::IN_ECHO;
                        break;
                    }
                    return TRUE;
                }
            }

            $this->status = self::IN_HTML;
        }
    
        switch ($this->status)
        {
            case self::IN_TAG:
            case self::IN_ECHO:
                $this->yylex_main();
                break;
            case self::IN_COMMENT:
                $data  = & $this->data;
                $i     = & $this->N;

                if (($pos=strpos($data, self::$end_comment, $i)) === FALSE) {
                    $this->error("unexpected end");
                }
                $this->value  = substr($data, $i, $pos);
                $this->token  = HG_Parser::T_COMMENT;
                $this->status = self::IN_NONE; 
                $i = $pos + 2;
                break;
            default:
                $this->yylex_html();
        }

        if (empty($this->token)) {
            if ($this->status != self::IN_NONE && $this->status != self::IN_HTML) {
                $this->Error("Unexpected end");
            }
            return FALSE;
        }

        return TRUE;

    }

    function yylex_html()
    {
        $data = &$this->data;
        $i    = &$this->N;

        foreach ($this->open_tags as $value => $status) {
            $pos = strpos($data, $value, $i);
            if ($pos === FALSE) {
                continue;
            }
            if (!isset($lowest_pos) || $lowest_pos > $pos) {
                $lowest_pos = $pos;
            }
        }

        if (isset($lowest_pos)) {
            $this->value  = substr($data, $i, $lowest_pos-$i);
            $this->token  = HG_Parser::T_HTML;
            $this->status = self::IN_NONE;
            $i += $lowest_pos - $i;
        } else {
            $this->value  = substr($data, $i);
            $this->token  = HG_Parser::T_HTML;
            $i = $this->length;
        }

        $this->line += substr_count($this->value, "\n");

    }


    function yylex_main()
    {
        $data = &$this->data;

        for ($i=&$this->N; is_null($this->token) && $i < $this->length; ++$i) {
            switch ($data[$i]) {

            /* strings {{{ */
            case '"':
            case "'":
                $end   = $data[$i];
                $value = "";
                while ($data[++$i] != $end) {
                    switch ($data[$i]) {
                    case "\\":
                        switch ($data[++$i]) {
                        case "n":
                            $value .= "\n";
                            break;
                        case "t":
                            $value .= "\t";
                            break;
                        default:
                            $value .= $data[$i];
                        }
                        break;
                    case $end:
                        --$i;
                        break 2;
                    default:
                        if ($data[$i] == "\n") {
                            $this->line++;
                        }
                        $value .= $data[$i];
                    }
                    if (!isset($data[$i+1])) {
                        $this->Error("unclosed string");
                    }
                }
                $this->value = $value;
                $this->token = HG_Parser::T_STRING;
                break;
            /* }}} */

            /* number {{{ */
            case '0': case '1': case '2': case '3': case '4':
            case '5': case '6': case '7': case '8': case '9': 
                $value = "";
                $dot   = FALSE;
                for ($e=0; $i < $this->length; ++$e, ++$i) {
                    switch ($data[$i]) {
                    case '0': case '1': case '2': case '3': case '4': 
                    case '5': case '6': case '7': case '8': case '9': 
                        $value .= $data[$i];
                        break;
                    case '.':
                        if (!$dot) {
                            $value .= ".";
                            $dot    = TRUE;
                        } else {
                            $this->error("Invalid number");
                        }
                        break;
                    default: 
                        if (!$this->is_token_end($data[$i]) &&
                            !isset(self::$operations[$data[$i]]) || $value[$e-1] == '.') {
                            $this->error("Unexpected '{$data[$i]}'");
                        }
                        $this->value = $value;
                        $this->token = HG_Parser::T_NUMERIC;
                        break 4; /* break the main loop */
                    }
                }
                break;
            /* }}} */

            case "\n":
                $this->line++;
            case " ": case "\t": case "\r": case "\f":
                break; /* whitespaces are ignored */
            default: 
                if (!$this->getTag() && !$this->getOperator()) {
                    $alpha = $this->getAlpha();
                    if ($alpha === FALSE) {
                        $this->error("error: unexpected ".substr($data, $i));
                    }
                    static $tag=NULL;
                    if (!$tag) {
                        $tag = Haanga_Extension::getInstance('Tag');
                    }
                    $value = $tag->isValid($alpha);
                    $this->token = $value ? $value : HG_Parser::T_ALPHA;
                    $this->value = $alpha;

                }
                break 2;
            }
        }

        if ($this->token == HG_Parser::T_CLOSE_TAG ||
            $this->token == HG_Parser::T_PRINT_CLOSE) {
            $this->status = self::IN_NONE;
        }

    }

    function getTag()
    {
        static $lencache = array();

        $i    = &$this->N;
        $data = substr($this->data, $i, 12);
        foreach (self::$tags as $value => $token) {
            if (!isset($lencache[$value])) {
                $lencache[$value] = strlen($value);
            }
            $len = $lencache[$value];
            if (strncmp($data, $value, $len) == 0) {
                if (isset($data[$len]) && !$this->is_token_end($data[$len])) {
                    /* probably a variable name TRUEfoo (and not TRUE) */
                    continue;
                }
                $this->token = $token;
                $this->value = $value;
                $i += $len;
                return TRUE;
            }
        }

        /* /end([a-zA-Z][a-zA-Z0-9]*)/ */
        if (strncmp($data, "end", 3) == 0) {
            $this->value = $this->getAlpha();
            $this->token = HG_Parser::T_CUSTOM_END;
            return TRUE;
        }
        
        return FALSE;
    }

    function Error($text)
    {
        throw new Haanga_Compiler_Exception($text." in ".$this->file.":".$this->line);
    }

    function getOperator()
    {
        static $lencache = array();
        $i    = &$this->N;
        $data = substr($this->data, $i, 12);
        foreach (self::$operations as $value => $token) {
            if (!isset($lencache[$value])) {
                $lencache[$value] = strlen($value);
            }
            $len = $lencache[$value];
            if (strncmp($data, $value, $len) == 0) {
                $this->token = $token;
                $this->value = $value;
                $i += $len;
                return TRUE;
            }
        }

        return FALSE;
    }


    /**
     *  Return TRUE if $letter is a valid "token_end". We use token_end
     *  to avoid confuse T_ALPHA TRUEfoo with TRUE and foo (T_ALPHA)
     *
     *  @param string $letter
     *
     *  @return bool
     */
    protected function is_token_end($letter)
    {
        /* [^a-zA-Z0-9_] */
        return !(
            ('a' <= $letter && 'z' >= $letter) ||
            ('A' <= $letter && 'Z' >= $letter) || 
            ('0' <= $letter && '9' >= $letter) || 
            $letter == "_" 
        );
    }

    function getAlpha()
    {
        /* [a-zA-Z_][a-zA-Z0-9_]* */
        $i    = &$this->N;
        $data = &$this->data;

        if (  !('a' <= $data[$i] && 'z' >= $data[$i]) &&
            !('A' <= $data[$i] && 'Z' >= $data[$i]) && $data[$i] != '_') {
            return FALSE;
        }

        $value  = "";
        for (; $i < $this->length; ++$i) {
            if (
                ('a' <= $data[$i] && 'z' >= $data[$i]) ||
                ('A' <= $data[$i] && 'Z' >= $data[$i]) || 
                ('0' <= $data[$i] && '9' >= $data[$i]) || 
                $data[$i] == "_"
            ) {
                $value .= $data[$i];
            } else {
                break;
            }
        }

        return $value;
    }

    function getLine()
    {
        return $this->line;
    }


    static function init($template, $compiler, $file='')
    {
        $lexer  = new Haanga_Compiler_Tokenizer($template, $compiler, $file);
        $parser = new Haanga_Compiler_Parser($lexer, $file);

        $parser->compiler = $compiler;

        for($i=0; ; $i++) {
            if  (!$lexer->yylex()) {
                break;
            }
            $parser->doParse($lexer->token, $lexer->value);
        }

        $parser->doParse(0, 0);
        return (array)$parser->body;

    }
}
