<?php

namespace TareqAS\Psym\Parser;

use PHPStan\PhpDocParser\Ast\NodeTraverser;
use PHPStan\PhpDocParser\Ast\NodeVisitor\CloningVisitor;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\Printer\Printer;

class DocParser
{
    private static $docParser;
    private $tokens;
    private $docNode;
    private $newDocNode;

    private function __construct(string $doc)
    {
        if (!self::$docParser) {
            $usedAttributes = ['lines' => true, 'indexes' => true];
            $constExprParser = new ConstExprParser(true, true, $usedAttributes);
            $typeParser = new TypeParser($constExprParser, true, $usedAttributes);
            self::$docParser = new PhpDocParser($typeParser, $constExprParser, true, true, $usedAttributes);
        }

        $lexer = new Lexer();
        $this->tokens = new TokenIterator($lexer->tokenize($doc));
        $this->docNode = self::$docParser->parse($this->tokens);

        $cloningTraverser = new NodeTraverser([new CloningVisitor()]);
        /** @var PhpDocNode $newPhpDocNode newDocNode */
        [$newPhpDocNode] = $cloningTraverser->traverse([$this->docNode]);
        $this->newDocNode = $newPhpDocNode;
    }

    public static function parse(string $doc): self
    {
        return new self($doc ?: '/** */');
    }

    public function getValue(string $tagName): string
    {
        if ($tag = $this->newDocNode->getTagsByName($tagName)) {
            $tag = array_shift($tag);

            return $tag->value->value;
        }

        return '';
    }

    public function getPropertyType(): ?TypeNode
    {
        if ($var = $this->newDocNode->getTagsByName('@var')) {
            return $var[0]->value->type;
        }

        return null;
    }

    public function getReturnType(): ?TypeNode
    {
        if ($type = $this->newDocNode->getReturnTagValues()) {
            return $type[0]->type;
        }

        return null;
    }

    public function addTag(string $tagName, string $value): self
    {
        $this->newDocNode->children[] = new PhpDocTagNode($tagName, new GenericTagValueNode($value));

        return $this;
    }

    public function removeTag(string $tagName): self
    {
        foreach ($this->newDocNode->children as $index => $tag) {
            if ($tag instanceof PhpDocTagNode && $tag->name === $tagName) {
                unset($this->newDocNode->children[$index]);
            }
        }

        return $this;
    }

    public function print(): string
    {
        $printer = new Printer();
        $doc = $printer->printFormatPreserving($this->newDocNode, $this->docNode, $this->tokens);

        return '/** */' === $doc ? '' : $doc;
    }
}
