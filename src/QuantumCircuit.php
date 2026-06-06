<?php

declare(strict_types=1);

namespace QuantumApp;

/**
 * Represents a quantum circuit, including its gates and metadata.
 */
class QuantumCircuit
{
    /** @var array<int, array> Sequence of gate operations */
    public array $gates = [];

    public function __construct(
        public readonly int $numQubits
    ) {
        if ($numQubits < 1 || $numQubits > 12) {
            throw new \InvalidArgumentException('Number of qubits must be between 1 and 12.');
        }
    }

    /**
     * Add a gate to the circuit.
     * 
     * @param string $type Gate type: h, x, y, z, s, t, rx, ry, rz, cx, cz, swap, measure, reset
     * @param int $target Target qubit index
     * @param array<int, int>|null $controls Optional control qubits
     * @param float|null $angle Rotation angle (for rx, ry, rz)
     * @param int $step The horizontal timeline step (slice) this gate resides in
     * @param int|null $target2 Optional second target qubit (for SWAP gate)
     */
    public function addGate(
        string $type,
        int $target,
        ?array $controls = null,
        ?float $angle = null,
        int $step = 0,
        ?int $target2 = null
    ): void {
        $type = strtolower($type);
        
        // Validation
        if ($target < 0 || $target >= $this->numQubits) {
            throw new \InvalidArgumentException("Target qubit index {$target} is out of bounds.");
        }
        
        if ($target2 !== null && ($target2 < 0 || $target2 >= $this->numQubits)) {
            throw new \InvalidArgumentException("Second target qubit index {$target2} is out of bounds.");
        }

        if ($controls !== null) {
            foreach ($controls as $control) {
                if ($control < 0 || $control >= $this->numQubits) {
                    throw new \InvalidArgumentException("Control qubit index {$control} is out of bounds.");
                }
                if ($control === $target || $control === $target2) {
                    throw new \InvalidArgumentException("Control qubit cannot be the same as a target qubit.");
                }
            }
        }

        $this->gates[] = [
            'type' => $type,
            'target' => $target,
            'controls' => $controls,
            'angle' => $angle,
            'step' => $step,
            'target2' => $target2
        ];
    }

    /**
     * Clear all gates in the circuit.
     */
    public function clear(): void
    {
        $this->gates = [];
    }

    /**
     * Return gates sorted by step.
     * @return array<int, array>
     */
    public function getGatesSorted(): array
    {
        $gates = $this->gates;
        usort($gates, function ($a, $b) {
            if ($a['step'] === $b['step']) {
                // If in same step, order by target index
                return $a['target'] <=> $b['target'];
            }
            return $a['step'] <=> $b['step'];
        });
        return $gates;
    }

    /**
     * Get the maximum step index in the circuit.
     */
    public function getMaxStep(): int
    {
        if (empty($this->gates)) {
            return 0;
        }
        return (int)max(array_column($this->gates, 'step'));
    }

    /**
     * Create a QuantumCircuit from an associative array structure (or JSON decoded object).
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['numQubits'])) {
            throw new \InvalidArgumentException("Missing 'numQubits' parameter.");
        }
        
        $circuit = new self((int)$data['numQubits']);
        
        if (isset($data['gates']) && is_array($data['gates'])) {
            foreach ($data['gates'] as $g) {
                $type = $g['type'] ?? '';
                $target = isset($g['target']) ? (int)$g['target'] : 0;
                $controls = isset($g['controls']) ? array_map('intval', (array)$g['controls']) : null;
                $angle = isset($g['angle']) ? (float)$g['angle'] : null;
                $step = isset($g['step']) ? (int)$g['step'] : 0;
                $target2 = isset($g['target2']) ? (int)$g['target2'] : null;
                
                $circuit->addGate($type, $target, $controls, $angle, $step, $target2);
            }
        }
        
        return $circuit;
    }

    /**
     * Export the circuit to equivalent PHP code representation.
     */
    public function exportToPhpCode(): string
    {
        $code = "<?php\n\n";
        $code .= "require_once 'src/Complex.php';\n";
        $code .= "require_once 'src/Statevector.php';\n";
        $code .= "require_once 'src/QuantumCircuit.php';\n";
        $code .= "require_once 'src/QuantumSimulator.php';\n\n";
        $code .= "use QuantumApp\\QuantumCircuit;\n";
        $code .= "use QuantumApp\\QuantumSimulator;\n\n";
        $code .= "// Create a quantum circuit with {$this->numQubits} qubits\n";
        $code .= "\$circuit = new QuantumCircuit({$this->numQubits});\n\n";

        $sortedGates = $this->getGatesSorted();
        foreach ($sortedGates as $g) {
            $type = strtoupper($g['type']);
            $target = $g['target'];
            $controlsStr = 'null';
            if ($g['controls'] !== null && !empty($g['controls'])) {
                $controlsStr = '[' . implode(', ', $g['controls']) . ']';
            }
            $angleStr = $g['angle'] !== null ? (string)$g['angle'] : 'null';
            $step = $g['step'];
            $target2Str = $g['target2'] !== null ? (string)$g['target2'] : 'null';

            $code .= sprintf(
                "\$circuit->addGate('%s', %d, %s, %s, %d, %s); // Step %d\n",
                strtolower($type),
                $target,
                $controlsStr,
                $angleStr,
                $step,
                $target2Str,
                $step
            );
        }

        $code .= "\n// Simulate the circuit\n";
        $code .= "\$simulator = new QuantumSimulator();\n";
        $code .= "\$result = \$simulator->run(\$circuit);\n\n";
        $code .= "// Print Dirac Notation of the final statevector\n";
        $code .= "echo \$result['statevector']->getDiracNotation() . PHP_EOL;\n";

        return $code;
    }
}
