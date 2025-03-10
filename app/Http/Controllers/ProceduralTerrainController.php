<?php

namespace App\Http\Controllers;

use App\Services\ProceduralTerrainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProceduralTerrainController extends Controller
{
    public function info()
    {
        return response()->json([
            'name' => 'Procedural Terrain API',
            'version' => '1.0.0',
            'description' => 'API for generating procedural terrain based on camera position and zoom level',
        ]);
    }

    /**
     * Genera un terreno procedural basado en los parámetros de la cámara
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate(Request $request)
    {
        // Validar los parámetros de entrada
        $validator = Validator::make($request->all(), [
            'world_width' => 'required|integer|min:1|max:100000',
            'world_height' => 'required|integer|min:1|max:100000',
            'camera_x' => 'required|integer|min:0',
            'camera_y' => 'required|integer|min:0',
            'camera_z' => 'required|numeric|min:0.1|max:10',
            'camera_width' => 'required|integer|min:1',
            'camera_height' => 'required|integer|min:1',
            'buffer' => 'required|integer|min:0|max:100',
            'seed' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Obtener los parámetros validados
        $worldWidth = $request->input('world_width');
        $worldHeight = $request->input('world_height');
        $cameraX = $request->input('camera_x');
        $cameraY = $request->input('camera_y');
        $cameraZoom = $request->input('camera_z');
        $cameraWidth = $request->input('camera_width');
        $cameraHeight = $request->input('camera_height');
        $buffer = $request->input('buffer');
        $seed = $request->input('seed');

        // Comprobar que las coordenadas de la cámara están dentro del mundo
        if ($cameraX >= $worldWidth || $cameraY >= $worldHeight) {
            return response()->json(['error' => 'Camera position out of world bounds'], 400);
        }

        // Definir el tileset (esto podría venir de una base de datos o archivo)
        $tileset = $this->getTileset();

        // Crear una instancia del servicio de terreno procedural
        $terrainService = new ProceduralTerrainService($tileset, $worldWidth, $worldHeight, $seed);

        // Generar el terreno para el área visible
        $terrain = $terrainService->generateVisibleArea(
            $cameraX,
            $cameraY,
            $cameraZoom,
            $cameraWidth,
            $cameraHeight,
            $buffer
        );

        // Obtener metadatos del terreno generado
        $metadata = $terrainService->getTerrainMetadata(
            $cameraX,
            $cameraY,
            $cameraWidth,
            $cameraHeight,
            $buffer
        );

        // Devolver el terreno generado y sus metadatos
        return response()->json([
            'metadata' => $metadata,
            'terrain' => $terrain,
            'tileset' => $tileset,
        ]);
    }

    /**
     * Devuelve la información del tileset
     *
     * @return array
     */
    private function getTileset(): array
    {
        // Esta información podría venir de la base de datos o un archivo de configuración
        return [
            [
                'id' => 0,
                'name' => 'deep_water',
                'walkable' => false,
                'image' => 'tiles/deep_water.png'
            ],
            [
                'id' => 1,
                'name' => 'water',
                'walkable' => false,
                'image' => 'tiles/water.png'
            ],
            [
                'id' => 2,
                'name' => 'sand',
                'walkable' => true,
                'image' => 'tiles/sand.png'
            ],
            [
                'id' => 3,
                'name' => 'grass',
                'walkable' => true,
                'image' => 'tiles/grass.png'
            ],
            [
                'id' => 4,
                'name' => 'plains',
                'walkable' => true,
                'image' => 'tiles/plains.png'
            ],
            [
                'id' => 5,
                'name' => 'forest',
                'walkable' => true,
                'image' => 'tiles/forest.png'
            ],
            [
                'id' => 6,
                'name' => 'forest_hill',
                'walkable' => true,
                'image' => 'tiles/forest_hill.png'
            ],
            [
                'id' => 7,
                'name' => 'hill',
                'walkable' => true,
                'image' => 'tiles/hill.png'
            ],
            [
                'id' => 8,
                'name' => 'mountain',
                'walkable' => false,
                'image' => 'tiles/mountain.png'
            ],
            [
                'id' => 9,
                'name' => 'snow',
                'walkable' => true,
                'image' => 'tiles/snow.png'
            ],
            [
                'id' => 10,
                'name' => 'desert',
                'walkable' => true,
                'image' => 'tiles/desert.png'
            ],
            [
                'id' => 11,
                'name' => 'swamp',
                'walkable' => true,
                'image' => 'tiles/swamp.png'
            ],
        ];
    }
}
