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
        $cacheKey = "terrain_chunk_{$this->seed}_{$startX}_{$startY}_{$width}_{$height}";

        // Intentar recuperar del caché para mejorar rendimiento
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $terrain = [];

        // Generar el terreno usando el algoritmo de ruido Perlin
        for ($y = 0; $y < $height; $y++) {
            $terrain[$y] = [];
            for ($x = 0; $x < $width; $x++) {
                $globalX = $startX + $x;
                $globalY = $startY + $y;

                // Usar diferentes frecuencias para generar capas de terreno
                $elevation = $this->perlinNoise($globalX / 100, $globalY / 100, $this->seed);
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
        Cache::put($cacheKey, $terrain, now()->addMinutes(60));

        return $terrain;
    }

    /**
     * Implementación simplificada de ruido Perlin
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

        // Implementación básica de ruido Perlin (simplificada)
        $value = sin($x * 10 + $seed) * cos($y * 10 + $seed) * 0.5 + 0.5;

        // Añadir una segunda capa de ruido para más variación
        $value = ($value + sin($x * 20 + $seed + 5) * cos($y * 20 + $seed + 5) * 0.25 + 0.25) / 1.5;

        // Restaurar la semilla original
        mt_srand($this->seed);

        return $value;
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
        // Agua profunda
        if ($elevation < 0.2) {
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
