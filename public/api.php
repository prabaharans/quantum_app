<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Complex.php';
require_once __DIR__ . '/../src/Statevector.php';
require_once __DIR__ . '/../src/QuantumCircuit.php';
require_once __DIR__ . '/../src/QuantumSimulator.php';

use QuantumApp\QuantumCircuit;
use QuantumApp\QuantumSimulator;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$storageFile = __DIR__ . '/../data/circuits.json';

// Default quantum circuit presets
$presets = [
    [
        'id' => 'bell_state',
        'name' => 'Bell State (Entanglement)',
        'description' => 'Creates the maximally entangled EPR pair (|00⟩ + |11⟩) / √2. Measuring one qubit instantly determines the state of the other.',
        'numQubits' => 2,
        'gates' => [
            ['type' => 'h', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 0],
            ['type' => 'cx', 'target' => 1, 'controls' => [0], 'angle' => null, 'step' => 1],
            ['type' => 'measure', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 2],
            ['type' => 'measure', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 2]
        ]
    ],
    [
        'id' => 'ghz_state',
        'name' => 'GHZ State (3-Qubit Entanglement)',
        'description' => 'Creates a Greenberger-Horne-Zeilinger state (|000⟩ + |111⟩) / √2. A classic example of multi-partite entanglement.',
        'numQubits' => 3,
        'gates' => [
            ['type' => 'h', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 0],
            ['type' => 'cx', 'target' => 1, 'controls' => [0], 'angle' => null, 'step' => 1],
            ['type' => 'cx', 'target' => 2, 'controls' => [1], 'angle' => null, 'step' => 2],
            ['type' => 'measure', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 3],
            ['type' => 'measure', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 3],
            ['type' => 'measure', 'target' => 2, 'controls' => null, 'angle' => null, 'step' => 3]
        ]
    ],
    [
        'id' => 'teleportation',
        'name' => 'Quantum Teleportation',
        'description' => 'Teleports a quantum state from Alice\'s qubit (q0) to Bob\'s qubit (q2) using an entangled pair (q1, q2) and classical conditional gates.',
        'numQubits' => 3,
        'gates' => [
            // Prepare Alice's qubit q0 in a superposition with Ry(pi/3)
            ['type' => 'ry', 'target' => 0, 'controls' => null, 'angle' => 1.0471975511965976, 'step' => 0], // pi/3
            // Create EPR pair between q1 and q2
            ['type' => 'h', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 1],
            ['type' => 'cx', 'target' => 2, 'controls' => [1], 'angle' => null, 'step' => 2],
            // Alice entangles q0 and q1
            ['type' => 'cx', 'target' => 1, 'controls' => [0], 'angle' => null, 'step' => 3],
            ['type' => 'h', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 4],
            // Alice measures q0 and q1
            ['type' => 'measure', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 5],
            ['type' => 'measure', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 5],
            // Bob applies conditional gates
            // Apply X on q2 if q1 was measured as 1
            ['type' => 'x', 'target' => 2, 'controls' => null, 'angle' => null, 'step' => 6, 'conditional' => ['qubit' => 1, 'value' => 1]],
            // Apply Z on q2 if q0 was measured as 1
            ['type' => 'z', 'target' => 2, 'controls' => null, 'angle' => null, 'step' => 7, 'conditional' => ['qubit' => 0, 'value' => 1]],
            // Measure q2 to verify state was teleported
            ['type' => 'measure', 'target' => 2, 'controls' => null, 'angle' => null, 'step' => 8]
        ]
    ],
    [
        'id' => 'deutsch_jozsa',
        'name' => 'Deutsch-Jozsa (Balanced Function)',
        'description' => 'Determines if a function f(x) is constant or balanced in a single query. Using a balanced oracle (f(x) = x), the query qubit (q0) is measured as |1⟩ with 100% probability.',
        'numQubits' => 2,
        'gates' => [
            // Prepare target qubit q1 in state |->
            ['type' => 'x', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 0],
            ['type' => 'h', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 1],
            ['type' => 'h', 'target' => 1, 'controls' => null, 'angle' => null, 'step' => 1],
            // Balanced oracle (CNOT with control q0, target q1)
            ['type' => 'cx', 'target' => 1, 'controls' => [0], 'angle' => null, 'step' => 2],
            // Apply Hadamard to query qubit
            ['type' => 'h', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 3],
            // Measure query qubit
            ['type' => 'measure', 'target' => 0, 'controls' => null, 'angle' => null, 'step' => 4]
        ]
    ]
];

$action = $_GET['action'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        
        if (!$data) {
            throw new \Exception('Invalid JSON request body.');
        }

        $postAction = $data['action'] ?? '';

        if ($postAction === 'simulate') {
            $circuitData = $data['circuit'] ?? null;
            if (!$circuitData) {
                throw new \Exception('Missing circuit data.');
            }

            $circuit = QuantumCircuit::fromArray($circuitData);
            $simulator = new QuantumSimulator();

            // Run step by step
            $stepByStepResult = $simulator->runStepByStep($circuit);
            
            // Format step by step response
            $stepByStepJson = [];
            foreach ($stepByStepResult as $stepIdx => $stepInfo) {
                $sv = $stepInfo['statevector'];
                $amplitudes = [];
                $numStates = 1 << $sv->numQubits;
                
                for ($i = 0; $i < $numStates; $i++) {
                    $real = $sv->amplitudes[2 * $i];
                    $imag = $sv->amplitudes[2 * $i + 1];
                    $binary = str_pad(decbin($i), $sv->numQubits, '0', STR_PAD_LEFT);
                    $amplitudes[] = [
                        'state' => $binary,
                        'real' => $real,
                        'imag' => $imag,
                        'prob' => $real * $real + $imag * $imag
                    ];
                }

                $stepByStepJson[$stepIdx] = [
                    'amplitudes' => $amplitudes,
                    'dirac' => $stepInfo['dirac'],
                    'blochSpheres' => $stepInfo['blochSpheres']
                ];
            }

            // Run shots (1024 shots)
            $shotsCounts = $simulator->runShots($circuit, 1024);

            // Export equivalent PHP code
            $phpCode = $circuit->exportToPhpCode();

            echo json_encode([
                'status' => 'success',
                'stepByStep' => $stepByStepJson,
                'shots' => $shotsCounts,
                'phpCode' => $phpCode
            ]);
            exit;
        }

        if ($postAction === 'save_circuit') {
            $name = trim($data['name'] ?? '');
            $circuitData = $data['circuit'] ?? null;

            if ($name === '' || !$circuitData) {
                throw new \Exception('Missing name or circuit data.');
            }

            // Load existing
            $saved = [];
            if (file_exists($storageFile)) {
                $saved = json_decode(file_get_contents($storageFile), true) ?: [];
            }

            // Update or add
            $updated = false;
            foreach ($saved as &$item) {
                if (strcasecmp($item['name'], $name) === 0) {
                    $item['circuit'] = $circuitData;
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                $saved[] = [
                    'name' => $name,
                    'circuit' => $circuitData
                ];
            }

            if (file_put_contents($storageFile, json_encode($saved, JSON_PRETTY_PRINT)) === false) {
                throw new \Exception('Failed to write circuit to storage. Check folder permissions.');
            }
            echo json_encode(['status' => 'success', 'message' => 'Circuit saved successfully.']);
            exit;
        }

        if ($postAction === 'delete_circuit') {
            $name = trim($data['name'] ?? '');
            if ($name === '') {
                throw new \Exception('Missing name parameter.');
            }

            $saved = [];
            if (file_exists($storageFile)) {
                $saved = json_decode(file_get_contents($storageFile), true) ?: [];
            }

            $saved = array_filter($saved, function ($item) use ($name) {
                return strcasecmp($item['name'], $name) !== 0;
            });

            $saved = array_values($saved);
            file_put_contents($storageFile, json_encode($saved, JSON_PRETTY_PRINT));
            echo json_encode(['status' => 'success', 'message' => 'Circuit deleted successfully.']);
            exit;
        }

        throw new \Exception("Unsupported POST action: {$postAction}");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'presets') {
            echo json_encode([
                'status' => 'success',
                'presets' => $presets
            ]);
            exit;
        }

        if ($action === 'list_saved') {
            $saved = [];
            if (file_exists($storageFile)) {
                $saved = json_decode(file_get_contents($storageFile), true) ?: [];
            }
            
            $list = array_map(function ($item) {
                return [
                    'name' => $item['name'],
                    'numQubits' => $item['circuit']['numQubits'] ?? 2
                ];
            }, $saved);

            echo json_encode([
                'status' => 'success',
                'circuits' => $list
            ]);
            exit;
        }

        if ($action === 'load_saved') {
            $name = $_GET['name'] ?? '';
            if ($name === '') {
                throw new \Exception('Missing name parameter.');
            }

            $saved = [];
            if (file_exists($storageFile)) {
                $saved = json_decode(file_get_contents($storageFile), true) ?: [];
            }

            foreach ($saved as $item) {
                if (strcasecmp($item['name'], $name) === 0) {
                    echo json_encode([
                        'status' => 'success',
                        'circuit' => $item['circuit']
                    ]);
                    exit;
                }
            }

            throw new \Exception("Circuit '{$name}' not found.");
        }

        throw new \Exception("Unsupported GET action: {$action}");
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
