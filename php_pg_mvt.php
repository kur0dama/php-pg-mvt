<?php
/*
Ported from
https://github.com/pramsey/minimal-mvt/blob/master/minimal-mvt.py
*/

class tile_request_handler {

    private $db = null;

    // Instantiate instance, parse initial argument array
    // Expects array of values from exploded uri in the following format:
    // [TABLE NAME, Z, X, Y.FORMAT]
    public function __construct($db, $init_arr) {
        $this->db = $db;
        $y_val = explode('.',$init_arr[3])[0];
        $fmt_val = explode('.',$init_arr[3])[1];
        $this->tile = array(
            'tbl'=>$init_arr[0],
            'zoom'=>intval($init_arr[1]),
            'x'=>intval($init_arr[2]),
            'y'=>intval($y_val),
            'fmt'=>$fmt_val
        );
    }

    // Do we have all the information we need?
    // Are all coordinates valid at this zoom level?
    private function tile_is_valid ($tile) {
        $has_params =  (isset($tile['tbl'], $tile['zoom'], $tile['x'], $tile['y']));
        $has_valid_fmt = (isset($tile['fmt']) || in_array($tile['fmt'], ['pbf','mvt']));
        if($has_params) {
            $size = pow(2,$tile['zoom']);
            $has_valid_dims = (
                $tile['x']<=$size &&
                $tile['y']<=$size &&
                $tile['x']>0 &&
                $tile['y']>0
            );
        }
        else { 
            $has_valid_dims = false;
        }
        // Are all conditions met?
        if ($has_params && $has_valid_fmt && $has_valid_dims) {
            return true;
        }
        else {
            return false;
        }
    }

    // Calculate envelope in Spherical Mercator projection (https://epsg.io/3857)
    private function tile_to_envelope($tile) {
        // Width of world in EPSG:3857
        $world_merc_max = 20037508.3427892;
        $world_merc_min = -1 * $world_merc_max;
        $world_merc_size = $world_merc_max - $world_merc_min;
        // Width in tiles at current zoom
        $world_tile_size = pow(2,$tile['zoom']);
        // Tile width in EPSG:3857
        $tile_merc_size = $world_merc_size / $world_tile_size;
        // Calculate geographic bounds from tile coordinates
        // XYZ tile coordinates are in "image space" so origin is
        // top-left, not bottom right
        $env = array(
            'xmin' => ($world_merc_min + ($tile_merc_size * $tile['x'])),
            'xmax' => ($world_merc_min + ($tile_merc_size * ($tile['x'] + 1))),
            'ymin' => ($world_merc_max - ($tile_merc_size * ($tile['y'] + 1))),
            'ymax' => ($world_merc_max - ($tile_merc_size * $tile['y']))
        );
        return $env;
    }

    // Generate SQL to materialize a query envelope in EPSG:3857.
    // Densify the edges a little so the envelope can be
    // safely converted to other coordinate systems.
    private function envelope_to_bounds_sql ($env) {
        $densify_factor=4;
        $seg_size = ($env['xmax'] - $env['xmin'])/$densify_factor;
        $sql = 'ST_Segmentize(ST_MakeEnvelope('.$env['xmin'].', '.$env['ymin'].', '.$env['xmax'].', '.$env['ymax'].', 3857),'.$seg_size.')';
        return $sql;
    }

    // Generate a SQL query to pull a tile worth of MVT data
    // from the table of interest.
    private function envelope_to_sql ($env) {
        $tbl_name = $this->tile['tbl'];
        $env_subquery = $this->envelope_to_bounds_sql($env);
        // Materialize the bounds
        // Select the relevant geometry and clip to MVT bounds
        // Convert to MVT format
        $sql = 'WITH bounds AS ( SELECT '.$env_subquery.' AS geom, '.$env_subquery.'::box2d AS b2d ), ';
        $sql = $sql.'mvtgeom AS ( ';
        $sql = $sql.'SELECT ST_AsMVTGeom(ST_Transform(t.geom_data, 3857), bounds.b2d, 4096, 256, false) AS geom from '.$tbl_name.' t, ';
        $sql = $sql.'bounds WHERE ST_Intersects(t.geom_data, ST_Transform(bounds.geom, 4326)) ) ';
        $sql = $sql.'SELECT ST_AsMVT(mvtgeom.*) as mvt FROM mvtgeom;';
        return $sql;
    }

    // use database connection, send query, receive results
    // immediately parse incoming LOB as stream, return output string
    private function sql_to_mvt ($sql) {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $data=stream_get_contents($stmt->fetch()['mvt']);
            return $data;
        } 
        catch (\PDOException $e) {
            exit($e->getMessage());
        }
    }

    // check tile parameters, produce mvt, output to requester
    public function pull_mvt () {
        $tile = $this->tile;
        if ($this->tile_is_valid($tile)) {
            $env = $this->tile_to_envelope($tile);
            $sql = $this->envelope_to_sql($env);
            $mvt = $this->sql_to_mvt($sql);
            return $mvt;
        }
        else {
            return null;
        }
    }

}
?>