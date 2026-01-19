use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

Route::post('/_boost/browser-logs', function(Request $request) {
    Log::channel('daily')->error('Browser Error', [
        'error' => $request->input('error'),
        'stack' => $request->input('stack'),
        'url' => $request->input('url')
    ]);
    return response()->json(['status' => 'logged']);
});