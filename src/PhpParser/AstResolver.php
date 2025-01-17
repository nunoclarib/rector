<?php

declare (strict_types=1);
namespace Rector\Core\PhpParser;

use RectorPrefix202209\Nette\Utils\FileSystem;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Php\PhpPropertyReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\TypeWithClassName;
use Rector\Core\PhpParser\Node\BetterNodeFinder;
use Rector\Core\Reflection\ReflectionResolver;
use Rector\Core\ValueObject\Application\File;
use Rector\Core\ValueObject\MethodName;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\NodeScopeAndMetadataDecorator;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\PhpDocParser\PhpParser\SmartPhpParser;
/**
 * The nodes provided by this resolver is for read-only analysis only!
 * They are not part of node tree processed by Rector, so any changes will not make effect in final printed file.
 */
final class AstResolver
{
    /**
     * Parsing files is very heavy performance, so this will help to leverage it
     * The value can be also null, as the method might not exist in the class.
     *
     * @var array<class-string, array<string, ClassMethod|null>>
     */
    private $classMethodsByClassAndMethod = [];
    /**
     * Parsing files is very heavy performance, so this will help to leverage it
     * The value can be also null, as the method might not exist in the class.
     *
     * @var array<string, Function_|null>>
     */
    private $functionsByName = [];
    public function __construct(
        /**
         * @readonly
         */
        private readonly SmartPhpParser $smartPhpParser,
        /**
         * @readonly
         */
        private readonly NodeScopeAndMetadataDecorator $nodeScopeAndMetadataDecorator,
        /**
         * @readonly
         */
        private readonly BetterNodeFinder $betterNodeFinder,
        /**
         * @readonly
         */
        private readonly NodeNameResolver $nodeNameResolver,
        /**
         * @readonly
         */
        private readonly ReflectionProvider $reflectionProvider,
        /**
         * @readonly
         */
        private readonly ReflectionResolver $reflectionResolver,
        /**
         * @readonly
         */
        private readonly NodeTypeResolver $nodeTypeResolver,
        /**
         * @readonly
         */
        private readonly \Rector\Core\PhpParser\ClassLikeAstResolver $classLikeAstResolver
    )
    {
    }
    /**
     * @return \PhpParser\Node\Stmt\Class_|\PhpParser\Node\Stmt\Trait_|\PhpParser\Node\Stmt\Interface_|\PhpParser\Node\Stmt\Enum_|null
     */
    public function resolveClassFromName(string $className)
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return null;
        }
        $classReflection = $this->reflectionProvider->getClass($className);
        return $this->resolveClassFromClassReflection($classReflection, $className);
    }
    /**
     * @return \PhpParser\Node\Stmt\Class_|\PhpParser\Node\Stmt\Trait_|\PhpParser\Node\Stmt\Interface_|\PhpParser\Node\Stmt\Enum_|null
     */
    public function resolveClassFromObjectType(TypeWithClassName $typeWithClassName)
    {
        return $this->resolveClassFromName($typeWithClassName->getClassName());
    }
    public function resolveClassMethodFromMethodReflection(MethodReflection $methodReflection) : ?ClassMethod
    {
        $classReflection = $methodReflection->getDeclaringClass();
        if (isset($this->classMethodsByClassAndMethod[$classReflection->getName()][$methodReflection->getName()])) {
            return $this->classMethodsByClassAndMethod[$classReflection->getName()][$methodReflection->getName()];
        }
        $fileName = $classReflection->getFileName();
        // probably native PHP method → un-parseable
        if ($fileName === null) {
            return null;
        }
        $nodes = $this->parseFileNameToDecoratedNodes($fileName);
        if ($nodes === null) {
            return null;
        }
        /** @var ClassLike[] $classLikes */
        $classLikes = $this->betterNodeFinder->findInstanceOf($nodes, ClassLike::class);
        $classLikeName = $classReflection->getName();
        $methodReflectionName = $methodReflection->getName();
        foreach ($classLikes as $classLike) {
            if (!$this->nodeNameResolver->isName($classLike, $classLikeName)) {
                continue;
            }
            $classMethod = $classLike->getMethod($methodReflectionName);
            if (!$classMethod instanceof ClassMethod) {
                continue;
            }
            $this->classMethodsByClassAndMethod[$classLikeName][$methodReflectionName] = $classMethod;
            return $classMethod;
        }
        // avoids looking for a class in a file where is not present
        $this->classMethodsByClassAndMethod[$classLikeName][$methodReflectionName] = null;
        return null;
    }
    /**
     * @return \PhpParser\Node\Stmt\ClassMethod|\PhpParser\Node\Stmt\Function_|null
     */
    public function resolveClassMethodOrFunctionFromCall(\PhpParser\Node\Expr\FuncCall|\PhpParser\Node\Expr\StaticCall|\PhpParser\Node\Expr\MethodCall $call, Scope $scope)
    {
        if ($call instanceof FuncCall) {
            return $this->resolveFunctionFromFuncCall($call, $scope);
        }
        return $this->resolveClassMethodFromCall($call);
    }
    public function resolveFunctionFromFunctionReflection(FunctionReflection $functionReflection) : ?Function_
    {
        if (isset($this->functionsByName[$functionReflection->getName()])) {
            return $this->functionsByName[$functionReflection->getName()];
        }
        $fileName = $functionReflection->getFileName();
        if ($fileName === null) {
            return null;
        }
        $nodes = $this->parseFileNameToDecoratedNodes($fileName);
        if ($nodes === null) {
            return null;
        }
        /** @var Function_[] $functions */
        $functions = $this->betterNodeFinder->findInstanceOf($nodes, Function_::class);
        foreach ($functions as $function) {
            if (!$this->nodeNameResolver->isName($function, $functionReflection->getName())) {
                continue;
            }
            // to avoid parsing missing function again
            $this->functionsByName[$functionReflection->getName()] = $function;
            return $function;
        }
        // to avoid parsing missing function again
        $this->functionsByName[$functionReflection->getName()] = null;
        return null;
    }
    /**
     * @param class-string $className
     */
    public function resolveClassMethod(string $className, string $methodName) : ?ClassMethod
    {
        $methodReflection = $this->reflectionResolver->resolveMethodReflection($className, $methodName, null);
        if (!$methodReflection instanceof MethodReflection) {
            return null;
        }
        $classMethod = $this->resolveClassMethodFromMethodReflection($methodReflection);
        if (!$classMethod instanceof ClassMethod) {
            return $this->locateClassMethodInTrait($methodName, $methodReflection);
        }
        return $classMethod;
    }
    public function resolveClassMethodFromCall(\PhpParser\Node\Expr\MethodCall|\PhpParser\Node\Expr\StaticCall $call) : ?ClassMethod
    {
        if ($call instanceof MethodCall) {
            $callerStaticType = $this->nodeTypeResolver->getType($call->var);
        } else {
            $callerStaticType = $this->nodeTypeResolver->getType($call->class);
        }
        if (!$callerStaticType instanceof TypeWithClassName) {
            return null;
        }
        $methodName = $this->nodeNameResolver->getName($call->name);
        if ($methodName === null) {
            return null;
        }
        return $this->resolveClassMethod($callerStaticType->getClassName(), $methodName);
    }
    /**
     * @return \PhpParser\Node\Stmt\Trait_|\PhpParser\Node\Stmt\Class_|\PhpParser\Node\Stmt\Interface_|\PhpParser\Node\Stmt\Enum_|null
     */
    public function resolveClassFromClassReflection(ClassReflection $classReflection, string $className)
    {
        return $this->classLikeAstResolver->resolveClassFromClassReflection($classReflection, $className);
    }
    /**
     * @return Trait_[]
     */
    public function parseClassReflectionTraits(ClassReflection $classReflection) : array
    {
        /** @var ClassReflection[] $classLikes */
        $classLikes = $classReflection->getTraits(\true);
        $traits = [];
        foreach ($classLikes as $classLike) {
            $fileName = $classLike->getFileName();
            if ($fileName === null) {
                continue;
            }
            $nodes = $this->parseFileNameToDecoratedNodes($fileName);
            if ($nodes === null) {
                continue;
            }
            /** @var Trait_|null $trait */
            $trait = $this->betterNodeFinder->findFirst($nodes, function (Node $node) use($classLike) : bool {
                return $node instanceof Trait_ && $this->nodeNameResolver->isName($node, $classLike->getName());
            });
            if (!$trait instanceof Trait_) {
                continue;
            }
            $traits[] = $trait;
        }
        return $traits;
    }
    /**
     * @return \PhpParser\Node\Stmt\Property|\PhpParser\Node\Param|null
     */
    public function resolvePropertyFromPropertyReflection(PhpPropertyReflection $phpPropertyReflection)
    {
        $classReflection = $phpPropertyReflection->getDeclaringClass();
        $fileName = $classReflection->getFileName();
        if ($fileName === null) {
            return null;
        }
        $nodes = $this->parseFileNameToDecoratedNodes($fileName);
        if ($nodes === null) {
            return null;
        }
        $nativeReflectionProperty = $phpPropertyReflection->getNativeReflection();
        $desiredPropertyName = $nativeReflectionProperty->getName();
        /** @var Property[] $properties */
        $properties = $this->betterNodeFinder->findInstanceOf($nodes, Property::class);
        foreach ($properties as $property) {
            if ($this->nodeNameResolver->isName($property, $desiredPropertyName)) {
                return $property;
            }
        }
        // promoted property
        return $this->findPromotedPropertyByName($nodes, $desiredPropertyName);
    }
    private function locateClassMethodInTrait(string $methodName, MethodReflection $methodReflection) : ?ClassMethod
    {
        $classReflection = $methodReflection->getDeclaringClass();
        $traits = $this->parseClassReflectionTraits($classReflection);
        /** @var ClassMethod|null $classMethod */
        $classMethod = $this->betterNodeFinder->findFirst($traits, function (Node $node) use($methodName) : bool {
            return $node instanceof ClassMethod && $this->nodeNameResolver->isName($node, $methodName);
        });
        $this->classMethodsByClassAndMethod[$classReflection->getName()][$methodName] = $classMethod;
        if ($classMethod instanceof ClassMethod) {
            return $classMethod;
        }
        return null;
    }
    /**
     * @return Stmt[]|null
     */
    private function parseFileNameToDecoratedNodes(string $fileName) : ?array
    {
        $stmts = $this->smartPhpParser->parseFile($fileName);
        if ($stmts === []) {
            return null;
        }
        $file = new File($fileName, FileSystem::read($fileName));
        return $this->nodeScopeAndMetadataDecorator->decorateNodesFromFile($file, $stmts);
    }
    /**
     * @param Stmt[] $stmts
     */
    private function findPromotedPropertyByName(array $stmts, string $desiredPropertyName) : ?Param
    {
        $class = $this->betterNodeFinder->findFirstInstanceOf($stmts, Class_::class);
        if (!$class instanceof Class_) {
            return null;
        }
        $constructClassMethod = $class->getMethod(MethodName::CONSTRUCT);
        if (!$constructClassMethod instanceof ClassMethod) {
            return null;
        }
        foreach ($constructClassMethod->getParams() as $param) {
            if ($param->flags === 0) {
                continue;
            }
            if ($this->nodeNameResolver->isName($param, $desiredPropertyName)) {
                return $param;
            }
        }
        return null;
    }
    private function resolveFunctionFromFuncCall(FuncCall $funcCall, Scope $scope) : ?Function_
    {
        if ($funcCall->name instanceof Expr) {
            return null;
        }
        if (!$this->reflectionProvider->hasFunction($funcCall->name, $scope)) {
            return null;
        }
        $functionReflection = $this->reflectionProvider->getFunction($funcCall->name, $scope);
        return $this->resolveFunctionFromFunctionReflection($functionReflection);
    }
}
