<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Database\QueryException;
use PDOException;

use Illuminate\Support\Facades\DB;

class SystemEnumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $enums = [
            "user.role" => [
                "admin",
                'reviewer',
                "user"
            ],
        ];

        $data = [];

        foreach ($enums as $k => $v){
            foreach ($v as $kk => $vv) {
                $data[] = [
                    'etype' => $k,
                    'name' => $vv,
                    'seqid' => $kk
                ];
            }
        }

        try {
            DB::table('system_enums')->insert($data);
        } catch (QueryException $e) {
            dd($e);
        } catch (PDOException $e) {
            dd($e);
        }
    }
}
