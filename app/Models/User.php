<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    // protected $fillable = [
    //     'password', 'email', 'gender', 'title',
    //     'familyname', 'givenname', 'nickname',
    //     'phone', 'birthdate', 'introduction',
    //     'address', 'city', 'state', 'country',
    //     'company'
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
        'password', 'deleted_at', 'created_at', 'updated_at'
    ];

    /**
     * Get the user title.
     */
    public function role(): HasMany
    {
        return $this->hasMany(UserRole::class)->with(['role'])->select(array(
                'id', 'user_id', 'role_id', 'ends_at', 'created_at', 'is_active'
            ));
    }

    /**
     * 
     * 
     */
    public function canJoinRoom($channelID){
        // $channel = Channel::with([
        //     'members' => function($query) {
        //         $query->where(['user_id' => $this->id]);
        //     }
        // ])->where('uuid', $channelID)->first();
        // $res = false;
        
        // if($channel && count($channel->members)){
        //     $res = true;
        // }
        
        // return $res;
    }
    
    /**
     * 
     * 
     * 
     */
    public function hasRole($role){
        $user = UserRole::where('role_id', SystemEnum::getIdByName('user.role', $role))->where('user_id', $this->id)->first();
        return $user ? true : false;
    }
}