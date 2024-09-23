<?php

namespace TareqAS\Psym\Parser;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class NodeVisitor extends NodeVisitorAbstract
{
    public $namespace = '';
    public $usedClasses = [];
    public $variables = [];
    public $docComments = [];
    public $kernelClass = null;
    public $className = null;
    public $doesItReturnClosure = false;

    public function beforeTraverse(array $nodes)
    {
        $node = end($nodes);

        if ($node instanceof Node\Stmt\Return_ && $node->expr instanceof Node\Expr\Closure) {
            $this->doesItReturnClosure = true;
        }
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_ && $node->name instanceof Node\Name) {
            $this->namespace = $node->name->toString();
        }

        if ($node instanceof Node\Stmt\UseUse) {
            $this->usedClasses[] = $node->name->toString();
        }

        if ($node instanceof Node\Stmt\Class_ && $node->name instanceof Node\Identifier) {
            $class = $node->name->toString();
            $this->className = $this->namespace ? $this->namespace.'\\'.$class : $class;
        }

        if ($node instanceof Node\Stmt\Function_ && $node->name instanceof Node\Identifier) {
            $functionName = $node->name->toString();
            $this->docComments[$functionName] = $this->getDocComment($node);
        }

        if ($node instanceof Node\Stmt\Property) {
            $propertyName = $node->props[0]->name->toString();
            $this->docComments[$propertyName] = $this->getDocComment($node);
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $className = $node->name->toString();
            $this->docComments[$className] = $this->getDocComment($node);
        }

        if ($node instanceof Node\Expr\Assign && $node->var instanceof Node\Expr\Variable && $node->expr instanceof Node\Expr\New_) {
            $this->variables[$node->var->name] = $node->expr->class->toString();
        }

        if ($node instanceof Node\Expr\New_ && $node->class instanceof Node\Name) {
            $name = $node->class->toString();
            if ('Symfony\Bundle\FrameworkBundle\Console\Application' === $this->getFullClassName($name)) {
                $kernelVar = $node->getArgs()[0]->value->name;
                $kernelName = $this->variables[$kernelVar] ?? null;
                $this->kernelClass = $kernelName ? $this->getFullClassName($kernelName) : null;
            }
        }
    }

    public function getFullClassName(string $class): string
    {
        $filter = array_filter($this->usedClasses, function ($usedClass) use ($class) {
            return substr($usedClass, -strlen($class)) === $class;
        });

        if (!$filter && class_exists($className = $this->namespace ? $this->namespace.'\\'.$class : $class)) {
            $class = $className;
        }

        return reset($filter) ?: $class;
    }

    private function getDocComment($node): string
    {
        if ($comment = $node->getDocComment()) {
            return $comment->getText();
        }

        return '';
    }
}
