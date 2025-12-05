<?php

$allFunctions = get_defined_functions();
$allFunctions = [
    'user' => get_extension_funcs('bcg729')
];


$code = "<?php\n\n";

// ===================== FUNCTIONS =====================
$calls = [];

foreach (['user'] as $scope) {

    foreach ($allFunctions[$scope] as $function) {
        $rfm = new ReflectionFunction($function);
        $params = [];

        foreach ($rfm->getParameters() as $p) {
            $params[] = [
                'isOptional'  => $p->isOptional(),
                'name'        => $p->getName(),
                'type'        => $p->getType() && method_exists($p->getType(), 'getName')
                    ? $p->getType()->getName()
                    : 'mixed',
                'default'     => $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null,
                'byReference' => $p->isPassedByReference(),
            ];
        }

        $returnType = 'mixed';
        if ($rfm->hasReturnType()) {
            $rType = $rfm->getReturnType();
            if (!empty($rType) && method_exists($rType, 'getName') && $rType->getName() !== 'void') {
                $returnType = $rType->getName();
            }
        }

        $calls[] = [
            'name'        => $function,
            'parameters'  => $params,
            'returnType'  => $returnType,
            'docComment'  => $rfm->getDocComment(),
        ];
    }
}

foreach ($calls as $call) {


    $code .= $call['docComment'] ? $call['docComment'] . "\n" : '';
    $code .= "function {$call['name']}(";
    $params = [];

    foreach ($call['parameters'] as $p) {
        $param = '';
        if ($p['type'] !== 'mixed') $param .= "{$p['type']} ";
        if ($p['byReference']) $param .= '&';
        $param .= "\${$p['name']}";
        if ($p['isOptional'] && $p['default'] !== null) {
            $param .= ' = ' . var_export($p['default'], true);
        }
        $params[] = $param;
    }

    $code .= implode(', ', $params) . ')';
    $returnType = $call['returnType'] !== 'mixed' ? $call['returnType'] : null;
    if ($returnType) $code .= ": {$returnType}";
    $code .= " {";

    if ($returnType === 'string') $code .= "\n    return \"\";";
    elseif ($returnType === 'int') $code .= "\n    return 0;";
    elseif ($returnType === 'bool') $code .= "\n    return false;";
    $code .= "\n}\n\n";
}

// ===================== CLASSES =====================
$allowClasses = ['bcg', 'opus', 'swoole', 'Resampler'];
foreach (get_declared_classes() as $className) {
    if (str_starts_with($className, '__')) continue;

    $found = false;
    foreach ($allowClasses as $allowClass) {
        if (str_contains(strtolower($className), strtolower($allowClass))) {
            $found = true;
            break;
        }
    }
    if (!$found) continue;

    $rc = new ReflectionClass($className);
    $code .= $rc->getDocComment() ? $rc->getDocComment() . "\n" : '';
    $code .= "class {$className} {\n";

    foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $className) continue;

        $code .= "    " . ($method->getDocComment() ? $method->getDocComment() . "\n    " : '');
        $code .= "public function {$method->getName()}(";
        $params = [];

        foreach ($method->getParameters() as $p) {
            $param = '';
            if ($p->getType() && method_exists($p->getType(), 'getName')) {
                $param .= $p->getType()->getName() . ' ';
            }
            if ($p->isPassedByReference()) $param .= '&';
            $param .= "\${$p->getName()}";
            if ($p->isOptional() && $p->isDefaultValueAvailable()) {
                $param .= ' = ' . var_export($p->getDefaultValue(), true);
            }
            $params[] = $param;
        }

        $code .= implode(', ', $params) . ')';

        // segurança extra no returnType
        $returnType = 'mixed';
        if ($method->hasReturnType()) {
            $rType = $method->getReturnType();
            if (!empty($rType) && method_exists($rType, 'getName') && $rType->getName() !== 'void') {
                $returnType = $rType->getName();
                $code .= ": {$returnType}";
            }
        }

        $code .= " {";
        if ($returnType === 'string') $code .= "\n        return \"\";";
        elseif ($returnType === 'int') $code .= "\n        return 0;";
        elseif ($returnType === 'bool') $code .= "\n        return false;";
        $code .= "\n    }\n\n";
    }

    $code .= "}\n\n";
}

file_put_contents('stubs.lotus.php', $code);
echo "✅ Gerado com segurança e retornos coerentes: stubs.lotus.php\n";
