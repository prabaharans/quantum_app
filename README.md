# QuantumPHP Studio ⚛️

A premium, interactive web-based quantum circuit simulator built from scratch using a custom statevector simulation engine in **PHP 8.5** and visualized in real time via a modern, glassmorphic HTML5/CSS3 dashboard with **3D WebGL Bloch Spheres** (powered by Three.js).

---

## 🌟 Key Features

*   **Custom PHP Quantum Engine**: Written in native object-oriented PHP 8.5 with strict typing. Simulates statevector changes for systems up to 12 qubits in microseconds.
*   **Step-by-Step Simulation Tracing**: View the statevector's mathematical progression step-by-step. Clicking any step in the visual editor instantly updates the Dirac notation, coordinate vectors, and 3D Bloch spheres.
*   **Interactive Circuit Builder Timeline**: Click to add, modify, or delete gates directly from a timeline grid. Includes automated rendering of multi-qubit link lines (CX, CZ, SWAP) and rotation angle input parsing (e.g. `pi/2`, `-pi/4`).
*   **3D Bloch Sphere Projections**: Renders interactive 3D Bloch Spheres for every qubit using WebGL. 
    *   *Entanglement awareness*: The arrows trace partial density matrices. When qubits become entangled (mixed state), the vector automatically shrinks towards the center and switches color from neon teal to amber-orange to visually indicate entanglement.
*   **Statistical Shot Engine**: Simulates collapsing measurement probabilities over 1024 runs, outputting a precise histogram of classical register outcomes.
*   **Classical Conditionals & Mid-Circuit Measurement**: Allows executing gates conditionally on prior measurement outcomes (crucial for executing teleportation circuits).
*   **One-Click Algorithms (Presets)**:
    *   **Bell State**: Creating maximum 2-qubit entanglement.
    *   **GHZ State**: Creating 3-qubit Greenberger-Horne-Zeilinger states.
    *   **Quantum Teleportation**: Demonstrating conditional operations and mid-circuit collapse.
    *   **Deutsch-Jozsa**: Proving quantum speedup with a single query on a balanced oracle.
*   **Live PHP Code Exporter**: Generates equivalent program code to run the same circuit programmatically using the backend PHP library.

---

## 📂 Project Architecture

```
quantum_app/
├── data/
│   └── circuits.json        # Database to store custom user-saved circuits
├── public/
│   ├── css/
│   │   └── style.css        # Glassmorphic UI styles & custom animations
│   ├── js/
│   │   └── app.js           # Timeline grid layout & 3D Three.js renderer
│   ├── api.php              # REST JSON API router for running simulations
│   └── index.php            # Main user interface dashboard
├── src/
│   ├── Complex.php          # OOP representation of complex numbers (a + bi)
│   ├── Statevector.php      # 2^n statevector arrays & multi-qubit linear algebra
│   ├── QuantumCircuit.php   # Circuit object representation & PHP code generator
│   └── QuantumSimulator.php # Step-by-step & collapsing shots simulation engines
├── tests/
│   └── test_simulator.php   # Mathematical verification unit tests
└── README.md
```

---

## ⚙️ Quick Start

### Prerequisites
*   PHP 8.5 or later.
*   Web server (like Apache or Nginx). The codebase is structured to be served from any directory, using relative API routes.

### Serving Locally
If you have Apache or Nginx already configured to serve `/var/www/html/`:
1.  Place the `quantum_app` folder in the web root.
2.  Ensure that the directory `data/` and file `data/circuits.json` are writable by the server user:
    ```bash
    chmod 777 data data/circuits.json
    ```
3.  Access the app in your browser:
    [http://localhost/quantum_app/public/index.php](http://localhost/quantum_app/public/index.php) (or your designated server port, e.g. `http://localhost:8000/quantum_app/public/index.php`)

Alternatively, start PHP's built-in server inside the `quantum_app` folder:
```bash
php -S localhost:8000 -t public/
```
Then visit: `http://localhost:8000`

---

## 🧪 Mathematical Verification

We include a unit test script to verify simulation logic:
```bash
php tests/test_simulator.php
```

This tests:
1.  Hadamard gate superposition properties.
2.  Pauli-X bit-flip operations.
3.  Bell State entanglement matching and partial trace Bloch vector collapse.
4.  Shots statistics sampling ranges.
5.  Chronological step-by-step vectors.

---

## 🛠️ Supported Quantum Gates

*   **H**: Hadamard Gate (Superposition).
*   **X / Y / Z**: Pauli Gates (Rotations around coordinate axes).
*   **S / T**: Phase shifts of $\pi/2$ and $\pi/4$.
*   **Rx / Ry / Rz**: Parametric rotations about coordinate axes.
*   **CX**: CNOT (Controlled-X) gate.
*   **CZ**: Controlled-Z gate.
*   **SWAP**: Swapping the states of two qubits.
*   **M**: Measurement gate (causing projective collapse).
*   **RESET**: Measuring the qubit and forcing it back into state $|0\rangle$.
