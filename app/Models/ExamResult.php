namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    protected $fillable = ['user_id', 'room_id', 'pdf_name', 'total_questions', 'ranking'];

    // Relación: Un resultado pertenece a un profesor (User)
    public function user() {
        return $this->belongsTo(User::class);
    }
}