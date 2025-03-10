document.addEventListener('DOMContentLoaded', function () {
    // Elementos del DOM
    const generateBtn = document.getElementById('generateBtn');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const errorMessage = document.getElementById('errorMessage');
    const terrainCanvas = document.getElementById('terrainCanvas');
    const ctx = terrainCanvas.getContext('2d');
    const metadataDiv = document.getElementById('metadata');
    const tileLegendDiv = document.getElementById('tileLegend');

    // Botones de navegación
    const moveUpBtn = document.getElementById('moveUp');
    const moveDownBtn = document.getElementById('moveDown');
    const moveLeftBtn = document.getElementById('moveLeft');
    const moveRightBtn = document.getElementById('moveRight');
    const zoomInBtn = document.getElementById('zoomIn');
    const zoomOutBtn = document.getElementById('zoomOut');

    // Estado actual del terreno
    let currentTerrain = null;
    let currentMetadata = null;

    // Colores para los diferentes tipos de tiles
    const tileColors = {
        'deep_water': '#0000AA',
        'water': '#0066CC',
        'sand': '#FFCC66',
        'grass': '#66CC66',
        'plains': '#AADD88',
        'forest': '#006633',
        'forest_hill': '#004D25',
        'hill': '#996633',
        'mountain': '#666666',
        'snow': '#FFFFFF',
        'desert': '#DDDD77',
        'swamp': '#669966'
    };

    // Inicializar
    setupEventListeners();
    updateCanvasSize();

    // Función para configurar event listeners
    function setupEventListeners() {
        const step = 5;
        // Evento para generar terreno
        generateBtn.addEventListener('click', generateTerrain);

        // Eventos de navegación de cámara
        moveUpBtn.addEventListener('click', () => moveCamera(0, -step));
        moveDownBtn.addEventListener('click', () => moveCamera(0, step));
        moveLeftBtn.addEventListener('click', () => moveCamera(-step, 0));
        moveRightBtn.addEventListener('click', () => moveCamera(step, 0));

        // Eventos de zoom
        zoomInBtn.addEventListener('click', () => changeZoom(0.1));
        zoomOutBtn.addEventListener('click', () => changeZoom(-0.1));

        // Actualizar tamaño del canvas cuando cambie el tamaño de tile
        document.getElementById('tileSize').addEventListener('change', updateCanvasSize);

        // Redimensionar canvas al cambiar el tamaño de la ventana
        window.addEventListener('resize', updateCanvasSize);
    }

    // Función para mover la cámara
    function moveCamera(deltaX, deltaY) {
        const cameraXInput = document.getElementById('cameraX');
        const cameraYInput = document.getElementById('cameraY');

        let newX = parseInt(cameraXInput.value) + deltaX;
        let newY = parseInt(cameraYInput.value) + deltaY;

        // Asegurar que la cámara no salga de los límites del mundo
        const worldWidth = parseInt(document.getElementById('worldWidth').value);
        const worldHeight = parseInt(document.getElementById('worldHeight').value);

        newX = Math.max(0, Math.min(newX, worldWidth - 1));
        newY = Math.max(0, Math.min(newY, worldHeight - 1));

        cameraXInput.value = newX;
        cameraYInput.value = newY;

        // Regenerar terreno con la nueva posición
        generateTerrain();
    }

    // Función para cambiar el zoom
    function changeZoom(delta) {
        const zoomInput = document.getElementById('cameraZoom');
        let newZoom = parseFloat(zoomInput.value) + delta;

        // Limitar zoom entre 0.1 y 10
        newZoom = Math.max(0.1, Math.min(newZoom, 10));
        zoomInput.value = newZoom.toFixed(1);

        // Regenerar terreno con el nuevo zoom
        generateTerrain();
    }

    // Función para actualizar el tamaño del canvas
    function updateCanvasSize() {
        const tileSize = parseInt(document.getElementById('tileSize').value);
        const cameraWidth = parseInt(document.getElementById('cameraWidth').value);
        const cameraHeight = parseInt(document.getElementById('cameraHeight').value);
        const buffer = parseInt(document.getElementById('buffer').value);

        // Tamaño del canvas basado en el área visible más buffer
        terrainCanvas.width = (cameraWidth + (buffer * 2)) * tileSize;
        terrainCanvas.height = (cameraHeight + (buffer * 2)) * tileSize;

        // Si ya tenemos terreno, redibujarlo
        if (currentTerrain) {
            renderTerrain();
        }
    }

    // Función para generar terreno
    function generateTerrain() {
        // Obtener todos los valores del formulario
        const formData = {
            world_width: parseInt(document.getElementById('worldWidth').value),
            world_height: parseInt(document.getElementById('worldHeight').value),
            camera_x: parseInt(document.getElementById('cameraX').value),
            camera_y: parseInt(document.getElementById('cameraY').value),
            camera_z: parseFloat(document.getElementById('cameraZoom').value),
            camera_width: parseInt(document.getElementById('cameraWidth').value),
            camera_height: parseInt(document.getElementById('cameraHeight').value),
            buffer: parseInt(document.getElementById('buffer').value)
        };

        // Añadir semilla si está definida
        const seedInput = document.getElementById('seed');
        if (seedInput.value) {
            formData.seed = parseInt(seedInput.value);
        }

        // Validar datos
        if (formData.camera_x >= formData.world_width ||
            formData.camera_y >= formData.world_height) {
            showError("La posición de la cámara está fuera de los límites del mundo");
            return;
        }

        // Mostrar indicador de carga
        //loadingIndicator.style.display = 'block';
        // TODO - Quitar comentario para mostrar el indicador de carga
        errorMessage.style.display = 'none';

        apiRequest(formData)
            .then(response => {
                // Guardar datos
                currentTerrain = response.terrain;
                currentTileset = response.tileset;
                currentMetadata = response.metadata;

                // Actualizar tamaño del canvas
                updateCanvasSize();

                // Renderizar terreno
                renderTerrain();

                // Mostrar metadatos
                metadataDiv.textContent = JSON.stringify(currentMetadata, null, 2);

                // Generar leyenda
                generateTileLegend();

                // Ocultar indicador de carga
                loadingIndicator.style.display = 'none';
            })
            .catch(error => {
                showError("Error al generar el terreno: " + error.message);
                loadingIndicator.style.display = 'none';
            });
    }

    function apiRequest(formData) {
        // a post request to the server
        return fetch('/api/terrain/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la petición al servidor');
                }
                return response.json();
            });
    }

    // Función para renderizar el terreno
    function renderTerrain() {
        if (!currentTerrain) return;

        const tileSize = parseInt(document.getElementById('tileSize').value);
        const showGrid = document.getElementById('showGrid').checked;

        // Limpiar canvas
        ctx.clearRect(0, 0, terrainCanvas.width, terrainCanvas.height);

        // Dibujar cada tile
        for (let y = 0; y < currentTerrain.length; y++) {
            for (let x = 0; x < currentTerrain[y].length; x++) {
                const tile = currentTerrain[y][x];
                const tileType = tile.type;

                // Dibujar el tile
                ctx.fillStyle = tileColors[tileType] || '#000000';
                ctx.fillRect(x * tileSize, y * tileSize, tileSize, tileSize);

                // Dibujar cuadrícula si está activada
                if (showGrid) {
                    ctx.strokeStyle = 'rgba(0, 0, 0, 0.2)';
                    ctx.strokeRect(x * tileSize, y * tileSize, tileSize, tileSize);
                }
            }
        }

        // Marcar el área visible (sin buffer)
        const buffer = currentMetadata.buffer;
        const visibleStartX = buffer * tileSize;
        const visibleStartY = buffer * tileSize;
        const visibleWidth = currentMetadata.camera.width * tileSize;
        const visibleHeight = currentMetadata.camera.height * tileSize;

        ctx.strokeStyle = 'rgba(255, 0, 0, 0.8)';
        ctx.lineWidth = 2;
        ctx.strokeRect(visibleStartX, visibleStartY, visibleWidth, visibleHeight);
    }

    // Función para generar la leyenda de tiles
    function generateTileLegend() {
        // Limpiar leyenda actual
        tileLegendDiv.innerHTML = '';

        // Crear un elemento para cada tipo de tile
        for (const type in tileColors) {
            const tileItem = document.createElement('div');
            tileItem.className = 'tile-item';

            const tileColor = document.createElement('div');
            tileColor.className = 'tile-color';
            tileColor.style.backgroundColor = tileColors[type];

            const tileName = document.createElement('span');
            tileName.textContent = type.replace('_', ' ');

            tileItem.appendChild(tileColor);
            tileItem.appendChild(tileName);
            tileLegendDiv.appendChild(tileItem);
        }
    }

    // Función para mostrar mensajes de error
    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.style.display = 'block';
    }

    // Generar un terreno inicial
    generateTerrain();
});
