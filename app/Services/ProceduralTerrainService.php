<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ProceduralTerrainService
{
    private $tileset;
    private $worldWidth;
    private $worldHeight;
    private $seed;

    /**
     * Constructor del servicio
     *
     * @param array $tileset Array con información de los tiles disponibles
     * @param int $worldWidth Ancho total del mundo en tiles
     * @param int $worldHeight Alto total del mundo en tiles
     * @param int $seed Semilla para la generación procedural
     */
    public function __construct(array $tileset, int $worldWidth, int $worldHeight, int $seed = null)
    {
        $this->tileset = $tileset;
        $this->worldWidth = $worldWidth;
        $this->worldHeight = $worldHeight;
        $this->seed = $seed ?? mt_rand(1, 999999);
    }

    /**
     * Genera un área de terreno visible y su buffer
     *
     * @param int $cameraX Posición X del centro de la cámara (en tiles)
     * @param int $cameraY Posición Y del centro de la cámara (en tiles)
     * @param float $cameraZoom Nivel de zoom de la cámara
     * @param int $cameraWidth Ancho visible de la cámara (en tiles)
     * @param int $cameraHeight Alto visible de la cámara (en tiles)
     * @param int $buffer Tamaño del buffer adicional alrededor del área visible
     * @return array Matriz de tiles generados para el área visible y su buffer
     */
    public function generateVisibleArea(
        int $cameraX,
        int $cameraY,
        float $cameraZoom,
        int $cameraWidth,
        int $cameraHeight,
        int $buffer
    ): array {
        // Calcular el área visible considerando el buffer
        $visibleX = max(0, $cameraX - intval($cameraWidth / 2) - $buffer);
        $visibleY = max(0, $cameraY - intval($cameraHeight / 2) - $buffer);
        $visibleWidth = $cameraWidth + ($buffer * 2);
        $visibleHeight = $cameraHeight + ($buffer * 2);

        // Asegurar que no nos pasamos de los límites del mundo
        $visibleX = min($visibleX, $this->worldWidth - $visibleWidth);
        $visibleY = min($visibleY, $this->worldHeight - $visibleHeight);
        $visibleWidth = min($visibleWidth, $this->worldWidth - $visibleX);
        $visibleHeight = min($visibleHeight, $this->worldHeight - $visibleY);

        // Generar solo el área visible y su buffer
        return $this->generateTerrainChunk($visibleX, $visibleY, $visibleWidth, $visibleHeight);
    }

    /**
     * Genera un segmento de terreno basado en coordenadas específicas
     *
     * @param int $startX Coordenada X inicial
     * @param int $startY Coordenada Y inicial
     * @param int $width Ancho del segmento a generar
     * @param int $height Alto del segmento a generar
     * @return array Matriz de tiles generados
     */
    private function generateTerrainChunk(int $startX, int $startY, int $width, int $height): array
    {
        // $cacheKey = "terrain_chunk_{$this->seed}_{$startX}_{$startY}_{$width}_{$height}";

        // // Intentar recuperar del caché para mejorar rendimiento
        // if (Cache::has($cacheKey)) {
        //     return Cache::get($cacheKey);
        // }

        $terrain = [];

        // Calcular el centro del mundo
        $centerX = $this->worldWidth / 2;
        $centerY = $this->worldHeight / 2;

        // Radio máximo (distancia del centro a la esquina)
        $maxRadius = sqrt(pow($this->worldWidth / 2, 2) + pow($this->worldHeight / 2, 2));

        // Factor de ajuste para la forma de la isla (valores más altos = isla más pequeña)
        $islandFactor = 4.0;

        // Generar el terreno usando el algoritmo de ruido Perlin
        for ($y = 0; $y < $height; $y++) {
            $terrain[$y] = [];
            for ($x = 0; $x < $width; $x++) {
                $globalX = $startX + $x;
                $globalY = $startY + $y;

                // Calcular la distancia desde este punto al centro del mundo
                $distanceToCenter = sqrt(pow($globalX - $centerX, 2) + pow($globalY - $centerY, 2));

                // Normalizar la distancia (0 en el centro, 1 en los bordes más lejanos)
                $normalizedDistance = $distanceToCenter / $maxRadius;

                // Aplicar una función que aumenta el efecto de la distancia (hace que los bordes sean más pronunciados)
                $islandGradient = pow($normalizedDistance * $islandFactor, 2);

                // Limitar el gradiente a un máximo de 1
                $islandGradient = min(1, $islandGradient);

                // Generar base de elevación con Perlin
                $baseElevation = $this->perlinNoise($globalX / 100, $globalY / 100, $this->seed);

                // Combinar con forma de isla: restar el gradiente de la isla para que el centro sea alto y los bordes bajos
                $elevation = max(0, $baseElevation - $islandGradient);

                // Usar diferentes frecuencias para generar capas de terreno
                $moisture = $this->perlinNoise($globalX / 120, $globalY / 120, $this->seed + 1000);
                $temperature = $this->perlinNoise($globalX / 150, $globalY / 150, $this->seed + 2000);

                // Determinar el tipo de tile basado en las variables ambientales
                $tileType = $this->determineTileType($elevation, $moisture, $temperature);

                $terrain[$y][$x] = [
                    'x' => $globalX,
                    'y' => $globalY,
                    'type' => $tileType,
                    'tile_id' => $this->getTileIdByType($tileType),
                    'elevation' => $elevation,
                ];
            }
        }

        // Guardar en caché para futuras solicitudes
        // Cache::put($cacheKey, $terrain, now()->addMinutes(60));

        return $terrain;
    }

    /**
     * Implementación mejorada de ruido Perlin con múltiples capas
     *
     * @param float $x Coordenada X
     * @param float $y Coordenada Y
     * @param int $seed Semilla para la generación
     * @return float Valor de ruido entre 0 y 1
     */
    private function perlinNoise(float $x, float $y, int $seed): float
    {
        // Establecer la semilla para la generación
        mt_srand($seed);

        // Capa 0: Frecuencia base (ruido aleatorio)
        $value0 = (mt_rand(0, 1000) / 1000) * 0.5;

        // Capa 1: Frecuencia baja (características grandes)
        $value1 = sin($x * 10 + $seed) * cos($y * 10 + $seed) * 0.5 + 0.5;

        // Capa 2: Frecuencia media (detalles medios)
        $value2 = sin($x * 20 + $seed + 5) * cos($y * 20 + $seed + 5) * 0.25 + 0.25;

        // Capa 3: Frecuencia alta (detalles pequeños)
        $value3 = sin($x * 35 + $seed + 10) * cos($y * 35 + $seed + 10) * 0.125 + 0.125;

        // Capa 4: Frecuencia muy alta con variación direccional (textura)
        $value4 = sin($x * 50 + $y * 30 + $seed + 15) * cos($y * 45 + $x * 25 + $seed + 20) * 0.0625 + 0.0625;

        // Combinar todas las capas con normalización para mantener el rango entre 0 y 1
        $combinedValue = ($value0 + $value1 + $value2 + $value3 + $value4) / 1.9375;

        // random factor
        $rfactor = 0.5;

        // Añadir un pequeño componente aleatorio para romper patrones persistentes
        $randomComponent = (mt_rand(0, 1000) / 1000) * $rfactor;
        $finalValue = min(1.0, max(0.0, $combinedValue + $randomComponent - $rfactor / 2));

        // Añadir un componente aleatorio mayor para romper patrones persistentes
        // $randomComponent = (mt_rand(0, 1000) / 1000) * 0.15;
        // $finalValue = min(1.0, max(0.0, $combinedValue + $randomComponent - 0.075));

        // Restaurar la semilla original
        mt_srand($this->seed);

        return $finalValue;
    }

    /**
     * Determina el tipo de tile basado en parámetros ambientales
     *
     * @param float $elevation Elevación del terreno (0-1)
     * @param float $moisture Humedad del terreno (0-1)
     * @param float $temperature Temperatura del terreno (0-1)
     * @return string Tipo de tile a utilizar
     */
    private function determineTileType(float $elevation, float $moisture, float $temperature): string
    {
        // Agua profunda - aumentamos el umbral para tener más agua en los bordes
        if ($elevation < 0.01) {
            return 'deep_water';
        }

        // Agua
        if ($elevation < 0.3) {
            return 'water';
        }

        // Arena/Playa
        if ($elevation < 0.35) {
            return 'sand';
        }

        // Montañas altas
        if ($elevation > 0.8) {
            if ($temperature < 0.3) {
                return 'snow';
            }
            return 'mountain';
        }

        // Colinas
        if ($elevation > 0.6) {
            if ($moisture > 0.6) {
                return 'forest_hill';
            }
            return 'hill';
        }

        // Terreno normal
        if ($moisture > 0.7) {
            return 'swamp';
        }

        if ($moisture > 0.5) {
            return 'forest';
        }

        if ($moisture > 0.3) {
            return 'grass';
        }

        if ($temperature > 0.7) {
            return 'desert';
        }

        // Terreno por defecto
        return 'plains';
    }

    /**
     * Obtiene el ID del tile basado en su tipo
     *
     * @param string $type Tipo de tile
     * @return int ID del tile en el tileset
     */
    private function getTileIdByType(string $type): int
    {
        // Mapeo de tipos de terreno a IDs del tileset
        $tileMap = [
            'deep_water' => 0,
            'water' => 1,
            'sand' => 2,
            'grass' => 3,
            'plains' => 4,
            'forest' => 5,
            'forest_hill' => 6,
            'hill' => 7,
            'mountain' => 8,
            'snow' => 9,
            'desert' => 10,
            'swamp' => 11,
        ];

        return $tileMap[$type] ?? 0;
    }

    /**
     * Devuelve los metadatos del terreno generado
     *
     * @param int $cameraX Posición X del centro de la cámara
     * @param int $cameraY Posición Y del centro de la cámara
     * @param int $cameraWidth Ancho visible de la cámara
     * @param int $cameraHeight Alto visible de la cámara
     * @param int $buffer Tamaño del buffer
     * @return array Metadatos del terreno generado
     */
    public function getTerrainMetadata(
        int $cameraX,
        int $cameraY,
        int $cameraWidth,
        int $cameraHeight,
        int $buffer
    ): array {
        $visibleX = max(0, $cameraX - intval($cameraWidth / 2) - $buffer);
        $visibleY = max(0, $cameraY - intval($cameraHeight / 2) - $buffer);
        $visibleWidth = $cameraWidth + ($buffer * 2);
        $visibleHeight = $cameraHeight + ($buffer * 2);

        return [
            'world_size' => [
                'width' => $this->worldWidth,
                'height' => $this->worldHeight,
            ],
            'camera' => [
                'x' => $cameraX,
                'y' => $cameraY,
                'width' => $cameraWidth,
                'height' => $cameraHeight,
            ],
            'visible_area' => [
                'x' => $visibleX,
                'y' => $visibleY,
                'width' => $visibleWidth,
                'height' => $visibleHeight,
            ],
            'buffer' => $buffer,
            'seed' => $this->seed,
        ];
    }
}
