<?php

namespace App\Http\Controllers;


use App\Http\Requests\FileStoreRequest;
use App\Http\Requests\FileUploadRequest;
use App\Http\Requests\FileUpdateRequest;
use App\Models\File;
use App\Models\Version;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FilesController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @param Version $version
     * @param FileUploadRequest $request
     */
    public function upload(Version $version, FileUploadRequest $request)
    {
        $upload = $request->file('file');

        $file = $version->files()->firstOrNew(['name' => $upload->getClientOriginalName()]);
        $file->content = file_get_contents($upload->path());
        $file->save();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $fileId
     * @return View
     */
    public function edit($fileId): View
    {
        $file = File::where('id', $fileId)->firstOrFail();
        return view('files.edit')
            ->with('file', $file);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param FileUpdateRequest $request
     * @param  int  $fileId
     * @return RedirectResponse
     */
    public function update(FileUpdateRequest $request, $fileId): RedirectResponse
    {
        $file = File::where('id', $fileId)->firstOrFail();
        try {
            $file->content = $request->file_content;
            $file->save();
        } catch (\Exception $e) {
            return redirect()->route('files.edit', ['file' => $file->id])->withInput()->withErrors([$e->getMessage()]);
        }

        $pyflakes = $this->lintContent($request->file_content);
        if ($pyflakes['return_value'] == 0) {
            return redirect()
                ->route('projects.edit', ['project' => $file->version->project->slug])
                ->withSuccesses([$file->name . ' saved']);
        } elseif (!empty($pyflakes[0])) {
            return redirect()->route('files.edit', ['file' => $file->id])
                ->withInput()
                ->withWarnings(explode("\n", $pyflakes[0]));
        }
        return redirect()->route('files.edit', ['file' => $file->id])
            ->withInput()
            ->withErrors(explode("\n", $pyflakes[1]));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request
     * @return View
     */
    public function create(Request $request): View
    {
        $version = Version::where('id', $request->get('version'))->firstOrFail();
        return view('files.create')->with('version', $version);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param FileStoreRequest $request
     * @return RedirectResponse
     */
    public function store(FileStoreRequest $request): RedirectResponse
    {
        $file = new File;
        try {
            $file->version_id = $request->version_id;
            $file->name = $request->name;
            $file->content = $request->file_content;
            $file->save();
        } catch (\Exception $e) {
            return redirect()->route('files.create')->withInput()->withErrors([$e->getMessage()]);
        }

        return redirect()->route('projects.edit', ['project' => $file->version->project->slug])->withSuccesses([$file->name.' saved']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $fileId
     * @return RedirectResponse
     */
    public function destroy($fileId): RedirectResponse
    {
        $file = File::where('id', $fileId)->firstOrFail();

        $project = $file->version->project;

        try {
            $file->delete();
        } catch (\Exception $e) {
            return redirect()->route('projects.edit', ['project' => $project->slug])
                ->withInput()
                ->withErrors([$e->getMessage()]);
        }

        return redirect()->route('projects.edit', ['project' => $project->slug])->withSuccesses([$file->name.' deleted']);
    }

    /**
     * @param string $content
     * @param string $command
     * @return array
     */
    public static function lintContent(string $content, string $command = "pyflakes"): array
    {
        $stdout = $stderr = '';
        $return_value = 255;
        $fds = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
            2 => array("pipe", "w")   // stderr is a pipe that the child will write to
        );
        $process = proc_open($command, $fds, $pipes, NULL, NULL);
        if (is_resource($process)) {
            fwrite($pipes[0], $content);
            fclose($pipes[0]);
            $stdout =  (string)stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = (string)stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $return_value = proc_close($process);
            // ToDo whatever you want to do with $stderr and the commands exit-code.
        } else {
            // ToDo whatever you want to do if the command fails to start
        }
        return [
            'return_value' => $return_value,
            0 => preg_replace('/<stdin>\:/', '', $stdout),
            1 => preg_replace('/<stdin>\:/', '', $stderr)
        ];
    }
}
