<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemEnum extends Model
{
    use Notifiable, SoftDeletes;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'deleted_at' => 'datetime',
    ];
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    // protected $fillable = [
    //     'etype', 'name', 'seqid'
    // ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id', 'created_at', 'updated_at', 'deleted_at'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'deleted_at', 'created_at', 'updated_at'
    ];
    
    public static function getIdByName($type, $name){
        $result = SystemEnum::where('etype', $type)->where('name', $name)->first();
        return $result?->id;
    }
    
    public static function getGroupByEtype($type){
        $enum = SystemEnum::where('etype', $type)->get();
        $ary = [];
        foreach($enum as $e){
            $ary[$e->name] = $e->id;
        }
        
        return $ary;
    }
    
    /*
     * 
     * For get multiple ID by Name
     * 
     * @param $type
     * @param String $name 
     */
    public static function getIdAryByName($type, $name){
        $nameAry = explode(",",$name);
        $enum = SystemEnum::select(['id'])->where('etype', $type)->whereIn('name', $nameAry)->get();
        $ary = [];
        foreach($enum as $e){
            if ($e?->id !== null) {
                $ary[] = $e->id;
            }
        }
        
        return $ary;
    }
    
    public function updateJSON(){
        $enums = SystemEnum::all();
        $toJSON = [];
        foreach($enums as $ee){
            $tmpObj = new \stdClass();
            $tmpObj->id = $ee['id'];
            $tmpObj->etype = $ee['etype'];
            $tmpObj->name = $ee['name'];
            $tmpObj->seqid = $ee['seqid'];
            array_push($toJSON,$tmpObj);
        }
        $jsonStr = json_encode($toJSON);
        
        file_put_contents(public_path('cdn') . "/enums.json", $jsonStr);
    }
}
