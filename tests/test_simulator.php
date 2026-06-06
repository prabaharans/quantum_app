<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use QuantumApp\Model\QuantumCircuit;
use QuantumApp\Model\QuantumSimulator;

function assertAlmostEqual(float $actual, float $expected, string $message, float $epsilon = 1e-5): void
{
    if (abs($actual - $expected) > $epsilon) {
        throw new \Exception("Assertion failed: {$message}. Expected {$expected}, got {$actual}");
    }
}

function runTests(): void
{
    echo "=== Running Quantum Simulator Tests (MVC Namespaces) ===\n\n";

    // Test 1: Single qubit H gate (Superposition)
    echo "Test 1: Single qubit Hadamard... ";
    $circuit = new QuantumCircuit(1);
    $circuit->addGate('h', 0);
    $sim = new QuantumSimulator();
    $result = $sim->run($circuit);
    $sv = $result['statevector'];
    
    // State should be 1/sqrt(2)|0> + 1/sqrt(2)|1>
    $invSqrt2 = 1.0 / sqrt(2.0);
    assertAlmostEqual($sv->amplitudes[0], $invSqrt2, "Real part of |0>");
    assertAlmostEqual($sv->amplitudes[1], 0.0, "Imag part of |0>");
    assertAlmostEqual($sv->amplitudes[2], $invSqrt2, "Real part of |1>");
    assertAlmostEqual($sv->amplitudes[3], 0.0, "Imag part of |1>");
    
    // Bloch sphere of q0 should be (1, 0, 0)
    $bloch = $sv->getBlochSphereCoordinates(0);
    assertAlmostEqual($bloch['x'], 1.0, "Bloch X");
    assertAlmostEqual($bloch['y'], 0.0, "Bloch Y");
    assertAlmostEqual($bloch['z'], 0.0, "Bloch Z");
    echo "PASSED\n";

    // Test 2: Single qubit X gate
    echo "Test 2: Pauli-X gate... ";
    $circuit = new QuantumCircuit(1);
    $circuit->addGate('x', 0);
    $result = $sim->run($circuit);
    $sv = $result['statevector'];
    assertAlmostEqual($sv->amplitudes[0], 0.0, "Real part of |0>");
    assertAlmostEqual($sv->amplitudes[2], 1.0, "Real part of |1>");
    
    // Bloch sphere of q0 should be (0, 0, -1)
    $bloch = $sv->getBlochSphereCoordinates(0);
    assertAlmostEqual($bloch['x'], 0.0, "Bloch X");
    assertAlmostEqual($bloch['y'], 0.0, "Bloch Y");
    assertAlmostEqual($bloch['z'], -1.0, "Bloch Z");
    echo "PASSED\n";

    // Test 3: Bell State creation (|00> + |11>) / sqrt(2)
    echo "Test 3: Bell State Entanglement... ";
    $circuit = new QuantumCircuit(2);
    $circuit->addGate('h', 0, null, null, 0);       // H on q0
    $circuit->addGate('cx', 1, [0], null, 1);      // CX with control q0, target q1
    $result = $sim->run($circuit);
    $sv = $result['statevector'];

    assertAlmostEqual($sv->amplitudes[0], $invSqrt2, "Real part of |00>"); // index 0
    assertAlmostEqual($sv->amplitudes[2], 0.0, "Real part of |01>");       // index 1
    assertAlmostEqual($sv->amplitudes[4], 0.0, "Real part of |10>");       // index 2
    assertAlmostEqual($sv->amplitudes[6], $invSqrt2, "Real part of |11>"); // index 3
    
    // Dirac notation test
    $dirac = $sv->getDiracNotation();
    if ($dirac !== "0.7071|00⟩ + 0.7071|11⟩") {
        throw new \Exception("Dirac notation test failed. Got: '{$dirac}'");
    }

    // Since they are maximally entangled, reduced density matrix of each qubit should be a mixed state (Bloch vector length = 0)
    $bloch0 = $sv->getBlochSphereCoordinates(0);
    $bloch1 = $sv->getBlochSphereCoordinates(1);
    assertAlmostEqual($bloch0['x'], 0.0, "q0 Bloch X");
    assertAlmostEqual($bloch0['y'], 0.0, "q0 Bloch Y");
    assertAlmostEqual($bloch0['z'], 0.0, "q0 Bloch Z");
    assertAlmostEqual($bloch1['x'], 0.0, "q1 Bloch X");
    assertAlmostEqual($bloch1['y'], 0.0, "q1 Bloch Y");
    assertAlmostEqual($bloch1['z'], 0.0, "q1 Bloch Z");
    echo "PASSED\n";

    // Test 4: Shots execution for Bell State
    echo "Test 4: Shots statistics... ";
    $shots = 1000;
    $counts = $sim->runShots($circuit, $shots);
    
    // States should only be "00" and "11"
    $count00 = $counts['00'] ?? 0;
    $count11 = $counts['11'] ?? 0;
    $total = $count00 + $count11;
    
    if ($total !== $shots) {
        throw new \Exception("Shots sum {$total} does not match requested shots {$shots}");
    }
    
    if ($count00 < 400 || $count00 > 600) {
        throw new \Exception("Statistics out of bounds: 00 was measured {$count00} times out of {$shots}");
    }
    echo "PASSED (Measured '00': {$count00}, '11': {$count11} out of {$shots})\n";

    // Test 5: Step-by-Step execution
    echo "Test 5: Step-by-step simulation... ";
    $steps = $sim->runStepByStep($circuit);
    
    // Step -1: |00>
    assertAlmostEqual($steps[-1]['statevector']->amplitudes[0], 1.0, "Step -1 amplitude");
    // Step 0: (|00> + |01>) / sqrt(2)  (since H is on q0, LSB)
    assertAlmostEqual($steps[0]['statevector']->amplitudes[0], $invSqrt2, "Step 0 q0 state");
    assertAlmostEqual($steps[0]['statevector']->amplitudes[2], $invSqrt2, "Step 0 q1 state");
    // Step 1: (|00> + |11>) / sqrt(2)
    assertAlmostEqual($steps[1]['statevector']->amplitudes[0], $invSqrt2, "Step 1 q0 state");
    assertAlmostEqual($steps[1]['statevector']->amplitudes[6], $invSqrt2, "Step 1 q3 state");
    echo "PASSED\n";

    // Test 6: CircuitRepository
    echo "Test 6: CircuitRepository persistence... ";
    $tempFile = sys_get_temp_dir() . '/quantum_test_circuits.json';
    $repo = new \QuantumApp\Model\CircuitRepository($tempFile);

    $circuitData = ['numQubits' => 2, 'gates' => []];
    $repo->save('TestCircuit', $circuitData);

    $loaded = $repo->findByName('testcircuit'); // case-insensitive
    if ($loaded === null) {
        throw new \Exception("CircuitRepository: saved circuit not found.");
    }
    if ($loaded['numQubits'] !== 2) {
        throw new \Exception("CircuitRepository: loaded circuit has wrong numQubits.");
    }

    $repo->deleteByName('TestCircuit');
    if ($repo->findByName('TestCircuit') !== null) {
        throw new \Exception("CircuitRepository: deleted circuit still found.");
    }

    // Cleanup temp file
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
    echo "PASSED\n";

    echo "\nAll tests completed successfully!\n";
}

try {
    runTests();
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
