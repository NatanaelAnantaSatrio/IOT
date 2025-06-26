<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Dashboard | G231.22.0167</title>

    <!-- Tailwind CSS for styling -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Paho MQTT Client Library (for browser) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/paho-mqtt/1.1.0/paho-mqtt.min.js"></script>

    <!-- Chart.js for graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>


    <style>
        /* Custom Font */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        /* Style for the status dot */
        .status-dot {
            height: 12px;
            width: 12px;
            border-radius: 50%;
            display: inline-block;
            transition: background-color 0.3s ease;
        }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 transition-colors duration-500">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">

        <!-- Header -->
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">IoT Monitoring Dashboard</h1>
            <div class="flex items-center space-x-2 mt-2">
                <span id="status-dot" class="status-dot bg-yellow-500 animate-pulse"></span>
                <p id="connection-status" class="text-md text-gray-500 dark:text-gray-400">Connecting to MQTT Broker...</p>
            </div>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Client ID: G231220167</p>
        </header>

        <!-- Data Cards -->
        <main class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

            <!-- Temperature Card (DHT11) -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 flex flex-col justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-500 dark:text-gray-400">Suhu (DHT11)</h2>
                    <p id="data-suhu" class="text-5xl font-bold text-blue-500 mt-2">-- °C</p>
                </div>
            </div>

            <!-- Humidity Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 flex flex-col justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-500 dark:text-gray-400">Kelembaban</h2>
                    <p id="data-kelembaban" class="text-5xl font-bold text-green-500 mt-2">-- %</p>
                </div>
            </div>

            <!-- Temperature Card (LM35) -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 flex flex-col justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-500 dark:text-gray-400">Suhu (LM35)</h2>
                    <p id="data-lm35" class="text-5xl font-bold text-orange-500 mt-2">-- °C</p>
                </div>
            </div>

            <!-- Status Card -->
            <div id="info-card" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 flex flex-col justify-between transition-colors duration-300">
                <div>
                    <h2 class="text-lg font-semibold text-gray-500 dark:text-gray-400">Status Sistem</h2>
                    <p id="data-info" class="text-4xl font-bold text-gray-700 dark:text-gray-200 mt-4">--</p>
                </div>
            </div>

        </main>

        <!-- Charts Section -->
        <section class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Grafik Suhu (°C)</h2>
                <canvas id="tempChart"></canvas>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">Grafik Kelembaban (%)</h2>
                <canvas id="humidityChart"></canvas>
            </div>
        </section>


    </div>

    <script>
        // --- MQTT Configuration ---
        const mqtt_host = "mqtt.revolusi-it.com";
        const mqtt_port = 9001;
        const mqtt_user = "usm";
        const mqtt_pass = "usmjaya1";
        const clientID = "web_G231220167_" + parseInt(Math.random() * 1000);

        const topic_sub = "iot/G231220167";

        // --- DOM Element References ---
        const statusDot = document.getElementById('status-dot');
        const connectionStatus = document.getElementById('connection-status');
        const dataSuhu = document.getElementById('data-suhu');
        const dataKelembaban = document.getElementById('data-kelembaban');
        const dataLm35 = document.getElementById('data-lm35');
        const dataInfo = document.getElementById('data-info');
        const infoCard = document.getElementById('info-card');

        // --- Chart Configuration ---
        const MAX_DATA_POINTS = 20; // Max number of data points to show on the charts

        function createChart(ctx, label, borderColor, backgroundColor) {
            return new Chart(ctx, {
                type: 'line',
                data: {
                    datasets: [{
                            label: `Suhu (DHT11)`,
                            borderColor: 'rgba(59, 130, 246, 1)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            data: [],
                            tension: 0.4
                        },
                        {
                            label: `Suhu (LM35)`,
                            borderColor: 'rgba(249, 115, 22, 1)',
                            backgroundColor: 'rgba(249, 115, 22, 0.1)',
                            fill: true,
                            data: [],
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'second',
                                tooltipFormat: 'HH:mm:ss',
                                displayFormats: {
                                    second: 'HH:mm:ss'
                                }
                            },
                            title: {
                                display: true,
                                text: 'Waktu'
                            }
                        },
                        y: {
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: 'Nilai'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                    }
                }
            });
        }

        function createHumidityChart(ctx) {
            return new Chart(ctx, {
                type: 'line',
                data: {
                    datasets: [{
                        label: 'Kelembaban',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        data: [],
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'second',
                                tooltipFormat: 'HH:mm:ss',
                                displayFormats: {
                                    second: 'HH:mm:ss'
                                }
                            },
                            title: {
                                display: true,
                                text: 'Waktu'
                            }
                        },
                        y: {
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: 'Persentase (%)'
                            }
                        }
                    }
                }
            });
        }

        const tempChartCtx = document.getElementById('tempChart').getContext('2d');
        const humidityChartCtx = document.getElementById('humidityChart').getContext('2d');
        const tempChart = createChart(tempChartCtx);
        const humidityChart = createHumidityChart(humidityChartCtx);


        // --- MQTT Client Initialization ---
        const client = new Paho.Client(mqtt_host, mqtt_port, clientID);

        // --- Client Callbacks ---
        client.onConnectionLost = onConnectionLost;
        client.onMessageArrived = onMessageArrived;

        // --- Connection Options ---
        const options = {
            timeout: 5,
            userName: mqtt_user,
            password: mqtt_pass,
            onSuccess: onConnect,
            onFailure: onFailure,
            useSSL: false
        };

        // --- Connect to Broker ---
        client.connect(options);

        // --- Callback Functions ---
        function onConnect() {
            console.log("Successfully connected to MQTT broker!");
            connectionStatus.textContent = "Connected";
            statusDot.classList.remove('bg-yellow-500', 'bg-red-500', 'animate-pulse');
            statusDot.classList.add('bg-green-500');
            client.subscribe(topic_sub);
            console.log(`Subscribed to topic: ${topic_sub}`);
        }

        function onFailure(responseObject) {
            console.error("Connection failed: " + responseObject.errorMessage);
            connectionStatus.textContent = "Connection Failed. Retrying...";
            statusDot.classList.remove('bg-yellow-500', 'bg-green-500');
            statusDot.classList.add('bg-red-500', 'animate-pulse');
        }

        function onConnectionLost(responseObject) {
            if (responseObject.errorCode !== 0) {
                console.log("Connection lost: " + responseObject.errorMessage);
                connectionStatus.textContent = "Connection Lost. Reconnecting...";
                statusDot.classList.remove('bg-green-500');
                statusDot.classList.add('bg-red-500', 'animate-pulse');
                client.connect(options);
            }
        }

        function onMessageArrived(message) {
            console.log("Message arrived on topic '" + message.destinationName + "': " + message.payloadString);

            try {
                const data = JSON.parse(message.payloadString);
                const now = new Date();

                // --- ADD THIS LINE ---
                saveDataToDatabase(data); // Send data to be saved in the database
                // --------------------

                // Update the dashboard cards
                if (data.suhu !== undefined) {
                    dataSuhu.textContent = `${data.suhu.toFixed(1)} °C`;
                }
                if (data.kelembaban !== undefined) {
                    dataKelembaban.textContent = `${data.kelembaban.toFixed(1)} %`;
                }
                if (data.lm35 !== undefined) {
                    dataLm35.textContent = `${data.lm35.toFixed(1)} °C`;
                }
                if (data.info !== undefined) {
                    dataInfo.textContent = data.info;
                    updateInfoCard(data.info);
                }

                // Update charts
                updateChartData(tempChart, 0, {
                    x: now,
                    y: data.suhu
                });
                updateChartData(tempChart, 1, {
                    x: now,
                    y: data.lm35
                });
                updateChartData(humidityChart, 0, {
                    x: now,
                    y: data.kelembaban
                });

            } catch (e) {
                console.error("Error parsing JSON from message: ", e);
            }
        }

        /**
         * Adds new data to a chart and removes the oldest data if it exceeds the max data points.
         * @param {Chart} chart The chart instance to update.
         * @param {number} datasetIndex The index of the dataset to update.
         * @param {object} newData The new data point object {x, y}.
         */
        function updateChartData(chart, datasetIndex, newData) {
            const dataset = chart.data.datasets[datasetIndex];
            dataset.data.push(newData);

            // Limit the number of data points
            if (dataset.data.length > MAX_DATA_POINTS) {
                dataset.data.shift(); // Remove the oldest data point
            }
            chart.update();
        }

        /**
         * Updates the color of the status card based on the info message.
         * @param {string} info The status message from the Arduino.
         */
        function updateInfoCard(info) {
            // Remove all potential background color classes
            infoCard.classList.remove('bg-green-200', 'dark:bg-green-800', 'bg-yellow-200', 'dark:bg-yellow-800', 'bg-red-200', 'dark:bg-red-800');
            infoCard.classList.remove('border-green-500', 'border-yellow-500', 'border-red-500');
            dataInfo.classList.remove('text-green-800', 'dark:text-green-100', 'text-yellow-800', 'dark:text-yellow-100', 'text-red-800', 'dark:text-red-100');


            info = info.toLowerCase();
            if (info.includes("bahaya")) {
                infoCard.classList.add('bg-red-200', 'dark:bg-red-800', 'border-red-500');
                dataInfo.classList.add('text-red-800', 'dark:text-red-100');
            } else if (info.includes("waspada")) {
                infoCard.classList.add('bg-yellow-200', 'dark:bg-yellow-800', 'border-yellow-500');
                dataInfo.classList.add('text-yellow-800', 'dark:text-yellow-100');
            } else { // Aman or Lembab
                infoCard.classList.add('bg-green-200', 'dark:bg-green-800', 'border-green-500');
                dataInfo.classList.add('text-green-800', 'dark:text-green-100');
            }
        }

        /**
         * Sends sensor data to the backend PHP script to save it.
         * @param {object} sensorData The JSON object received from MQTT.
         */
        async function saveDataToDatabase(sensorData) {
            try {
                const response = await fetch('save_data.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(sensorData),
                });
                const result = await response.json();
                console.log('Save to DB:', result.message);
            } catch (error) {
                console.error('Failed to save data to database:', error);
            }
        }
    </script>
</body>

</html>