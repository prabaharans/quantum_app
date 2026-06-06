<?php

declare(strict_types=1);

namespace QuantumApp;

/**
 * Simulator engine that executes Quantum Circuits.
 */
class QuantumSimulator
{
    /**
     * Run the circuit and return the final statevector and metadata.
     * This runs an "ideal" simulation where measurements do not collapse the state,
     * providing the full statevector.
     */
    public function run(QuantumCircuit $circuit): array
    {
        $statevector = new Statevector($circuit->numQubits);
        $gates = $circuit->getGatesSorted();

        foreach ($gates as $g) {
            $type = $g['type'];
            $target = $g['target'];
            $controls = $g['controls'];
            $angle = $g['angle'];
            $target2 = $g['target2'];

            if ($type === 'measure' || $type === 'reset') {
                // In ideal mode, we skip collapse to preserve the full statevector
                continue;
            }

            if ($type === 'swap') {
                $statevector->applySwap($target, $target2, $controls);
            } else {
                $gateType = $type;
                if ($type === 'cx') {
                    $gateType = 'x';
                } elseif ($type === 'cz') {
                    $gateType = 'z';
                }
                $matrix = self::getGateMatrix($gateType, $angle);
                $statevector->applyOneQubitGate($target, $matrix, $controls);
            }
        }

        return [
            'statevector' => $statevector,
            'probabilities' => $statevector->getProbabilities(),
            'dirac' => $statevector->getDiracNotation(),
            'blochSpheres' => $this->getBlochCoordinates($statevector)
        ];
    }

    /**
     * Run step-by-step, returning the statevector state at each step index.
     * This is useful for visual debugging in the frontend.
     */
    public function runStepByStep(QuantumCircuit $circuit): array
    {
        $numQubits = $circuit->numQubits;
        $statevector = new Statevector($numQubits);
        
        $sortedGates = $circuit->getGatesSorted();
        $maxStep = $circuit->getMaxStep();
        if (empty($sortedGates)) {
            $maxStep = 0;
        }

        // Group gates by step
        $gatesByStep = [];
        foreach ($sortedGates as $g) {
            $gatesByStep[$g['step']][] = $g;
        }

        $stepsData = [];

        // Step -1: Initial state |00...0>
        $stepsData[-1] = [
            'statevector' => clone $statevector,
            'probabilities' => $statevector->getProbabilities(),
            'dirac' => $statevector->getDiracNotation(),
            'blochSpheres' => $this->getBlochCoordinates($statevector),
            'gates' => []
        ];

        for ($s = 0; $s <= $maxStep; $s++) {
            $stepGates = $gatesByStep[$s] ?? [];
            
            foreach ($stepGates as $g) {
                $type = $g['type'];
                $target = $g['target'];
                $controls = $g['controls'];
                $angle = $g['angle'];
                $target2 = $g['target2'];

                if ($type === 'measure' || $type === 'reset') {
                    // Skip collapse during step-by-step vector tracking
                    continue;
                }

                if ($type === 'swap') {
                    $statevector->applySwap($target, $target2, $controls);
                } else {
                    $gateType = $type;
                    if ($type === 'cx') {
                        $gateType = 'x';
                    } elseif ($type === 'cz') {
                        $gateType = 'z';
                    }
                    $matrix = self::getGateMatrix($gateType, $angle);
                    $statevector->applyOneQubitGate($target, $matrix, $controls);
                }
            }

            $stepsData[$s] = [
                'statevector' => clone $statevector,
                'probabilities' => $statevector->getProbabilities(),
                'dirac' => $statevector->getDiracNotation(),
                'blochSpheres' => $this->getBlochCoordinates($statevector),
                'gates' => $stepGates
            ];
        }

        return $stepsData;
    }

    /**
     * Run the circuit with measurement collapse shot-by-shot to simulate a real quantum computer.
     * Supports mid-circuit measurements and conditional gates.
     * 
     * Each gate can have a conditional block like:
     * 'conditional' => ['qubit' => 1, 'value' => 1]
     */
    public function runShots(QuantumCircuit $circuit, int $shots = 1024): array
    {
        $numQubits = $circuit->numQubits;
        $sortedGates = $circuit->getGatesSorted();
        $counts = [];

        // Check if there are any measurement gates
        $hasMeasurements = false;
        foreach ($sortedGates as $g) {
            if ($g['type'] === 'measure') {
                $hasMeasurements = true;
                break;
            }
        }

        for ($shot = 0; $shot < $shots; $shot++) {
            $statevector = new Statevector($numQubits);
            $classicalRegister = array_fill(0, $numQubits, 0);
            $qubitsMeasured = array_fill(0, $numQubits, false);

            foreach ($sortedGates as $g) {
                $type = $g['type'];
                $target = $g['target'];
                $controls = $g['controls'];
                $angle = $g['angle'];
                $target2 = $g['target2'];

                // Check conditional execution (based on classical register)
                if (isset($g['conditional']) && $g['conditional'] !== null) {
                    $cQubit = (int)$g['conditional']['qubit'];
                    $cVal = (int)$g['conditional']['value'];
                    if ($classicalRegister[$cQubit] !== $cVal) {
                        continue;
                    }
                }

                if ($type === 'measure') {
                    $outcome = $statevector->measure($target);
                    $classicalRegister[$target] = $outcome;
                    $qubitsMeasured[$target] = true;
                } elseif ($type === 'reset') {
                    $outcome = $statevector->measure($target);
                    if ($outcome === 1) {
                        // Apply X gate to flip back to 0
                        $statevector->applyOneQubitGate($target, self::getGateMatrix('x'));
                    }
                    $classicalRegister[$target] = 0;
                } elseif ($type === 'swap') {
                    $statevector->applySwap($target, $target2, $controls);
                } else {
                    $gateType = $type;
                    if ($type === 'cx') {
                        $gateType = 'x';
                    } elseif ($type === 'cz') {
                        $gateType = 'z';
                    }
                    $matrix = self::getGateMatrix($gateType, $angle);
                    $statevector->applyOneQubitGate($target, $matrix, $controls);
                }
            }

            // Construct result key based on measured qubits (or all qubits if no explicit measurements exist)
            $keyArr = [];
            for ($q = $numQubits - 1; $q >= 0; $q--) {
                if ($hasMeasurements) {
                    if ($qubitsMeasured[$q]) {
                        $keyArr[] = (string)$classicalRegister[$q];
                    }
                } else {
                    // If no measurement gates, we measure all qubits at the end to get statistics
                    $outcome = $statevector->measure($q);
                    $keyArr[] = (string)$outcome;
                }
            }

            $key = implode('', $keyArr);
            if ($key === '') {
                $key = 'N/A';
            }
            
            if (!isset($counts[$key])) {
                $counts[$key] = 0;
            }
            $counts[$key]++;
        }

        // Sort keys binarily
        ksort($counts);

        return $counts;
    }

    /**
     * Compute Bloch coordinates for all qubits in a statevector.
     */
    private function getBlochCoordinates(Statevector $sv): array
    {
        $coords = [];
        for ($q = 0; $q < $sv->numQubits; $q++) {
            $coords[$q] = $sv->getBlochSphereCoordinates($q);
        }
        return $coords;
    }

    /**
     * Returns the 2x2 complex unitary matrix representation of a single qubit gate.
     */
    public static function getGateMatrix(string $type, ?float $angle = null): array
    {
        $invSqrt2 = 1.0 / sqrt(2.0);
        switch ($type) {
            case 'h':
                return [
                    [$invSqrt2, 0.0, $invSqrt2, 0.0],
                    [$invSqrt2, 0.0, -$invSqrt2, 0.0]
                ];
            case 'x':
                return [
                    [0.0, 0.0, 1.0, 0.0],
                    [1.0, 0.0, 0.0, 0.0]
                ];
            case 'y':
                return [
                    [0.0, 0.0, 0.0, -1.0],
                    [0.0, 1.0, 0.0, 0.0]
                ];
            case 'z':
                return [
                    [1.0, 0.0, 0.0, 0.0],
                    [0.0, 0.0, -1.0, 0.0]
                ];
            case 's':
                return [
                    [1.0, 0.0, 0.0, 0.0],
                    [0.0, 0.0, 0.0, 1.0]
                ];
            case 't':
                return [
                    [1.0, 0.0, 0.0, 0.0],
                    [0.0, 0.0, cos(M_PI / 4.0), sin(M_PI / 4.0)]
                ];
            case 'rx':
                $theta = $angle ?? 0.0;
                $cos = cos($theta / 2.0);
                $sin = sin($theta / 2.0);
                return [
                    [$cos, 0.0, 0.0, -$sin],
                    [0.0, -$sin, $cos, 0.0]
                ];
            case 'ry':
                $theta = $angle ?? 0.0;
                $cos = cos($theta / 2.0);
                $sin = sin($theta / 2.0);
                return [
                    [$cos, 0.0, -$sin, 0.0],
                    [$sin, 0.0, $cos, 0.0]
                ];
            case 'rz':
                $theta = $angle ?? 0.0;
                $cos = cos($theta / 2.0);
                $sin = sin($theta / 2.0);
                return [
                    [$cos, -$sin, 0.0, 0.0],
                    [0.0, 0.0, $cos, $sin]
                ];
            default:
                throw new \InvalidArgumentException("Unknown single-qubit gate type: {$type}");
        }
    }
}
