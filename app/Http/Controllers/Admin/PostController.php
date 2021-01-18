<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Category;
use App\Post;
use App\Tag;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AuthorPostApproved;
use App\Notifications\NewPostNotify;
use App\User;
use App\Subscriber;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $posts = Post::latest()->get();
        return view('admin.post.index',compact('posts'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        $categories = Category::all();
        $tags = Tag::all(); 
        return view('admin.post.create', compact('categories','tags'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request,[
            'title' => 'required',
            'image' => 'required',
            'categories' => 'required',
            'tags' => 'required',
            'body' => 'required',

            ]);

        $image = $request->file('image');
        $slug = Str::slug($request->title);

        if (isset($image)) 
        {
            //Make Unique Name For Image
            $currentDate = Carbon::now()->toDateString();
            $imageName = $slug.'-'.$currentDate.'-'.uniqid().'.'.$image->getClientOriginalExtension();
        
            if (!Storage::disk('public')->exists('post'))
            {
                Storage::disk('public')->makeDirectory('post');
            }

            $postImage = Image::make($image)->resize(1600,1066)->save('foo'.$image->getClientOriginalExtension());
            Storage::disk('public')->put('post/'.$imageName,$postImage);

        }
        else 
        {
            $imageName = "default.png";
        }

        //DB Update
        $post = new Post();
        $post->user_id = Auth::id();
        $post->title = $request->title;
        $post->slug = $slug;
        $post->image = $imageName;
        $post->body = $request->body;
        if (isset($request->status)) 
        {
            $post->status = true;
        }else 
        {
            $post->status = false;
        }
        $post->is_approved = true;
        $post->save();

        //Relation
        $post->categories()->attach($request->categories);
        $post->tags()->attach($request->tags);

        
        // subscriber notification
        $subscribers = Subscriber::all();
        foreach ($subscribers as $subscriber) {
            Notification::route('mail', $subscriber->email)->notify(new NewPostNotify($post));
        }

        Toastr::success('Post Sussessfully Create :)', 'Success');
        return  redirect()->route('admin.post.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function show(Post $post)
    {
       
        return view('admin.post.show', compact('post'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function edit(Post $post)
    {
        $categories = Category::all();
        $tags = Tag::all(); 
        return view('admin.post.edit', compact('post', 'categories','tags'));

        Toastr::success('Post Sussessfully Updated :)', 'Success');
        return  redirect()->route('admin.post.index');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Post $post)
    {
        $this->validate($request,[
            'title' => 'required',
            'image' => 'image',
            'categories' => 'required',
            'tags' => 'required',
            'body' => 'required',

            ]);

        $image = $request->file('image');
        $slug = Str::slug($request->title);

        if (isset($image)) 
        {
            //Make Unique Name For Image
            $currentDate = Carbon::now()->toDateString();
            $imageName = $slug.'-'.$currentDate.'-'.uniqid().'.'.$image->getClientOriginalExtension();
        
            if (!Storage::disk('public')->exists('post'))
            {
                Storage::disk('public')->makeDirectory('post');
            }
            //Delete Old Post Image
            if (Storage::disk('public')->exists('post/'.$post->image)) 
            {
                Storage::disk('public')->delete('post/'.$post->image);
                
            }

            $postImage = Image::make($image)->resize(1600,1066)->save('foo'.$image->getClientOriginalExtension());
            Storage::disk('public')->put('post/'.$imageName,$postImage);

        }
        else 
        {
            $imageName = $post->image;
        }

        //DB Update
        $post->user_id = Auth::id();
        $post->title = $request->title;
        $post->slug = $slug;
        $post->image = $imageName;
        $post->body = $request->body;
        if (isset($request->status)) 
        {
            $post->status = true;
        }else 
        {
            $post->status = false;
        }
        $post->is_approved = true;
        $post->save();

        //Relation
        $post->categories()->sync($request->categories);
        $post->tags()->sync($request->tags);

        Toastr::success('Post Sussessfully Updated :)', 'Success');
        return  redirect()->route('admin.post.index');
    }


    //Post Aproved Chacked
        public function pending()
        {
            $posts = Post::where('is_approved',false)->get();
            return view('admin.post.pending', compact('posts'));
        }


        public function approval($id)
        {
            $post = Post::find($id);
            if($post->is_approved == false)
            {
            $post->is_approved = true;
            $post->save();
            $post->user->notify(new AuthorPostApproved($post));
            $subscribers = Subscriber::all();
            foreach ($subscribers as $subscriber) {
                Notification::route('mail', $subscriber->email)->notify(new AuthorPostApproved($post));
            }
            Toastr::success('Post Sussessfully Approved :)','Success');
            }
            else
            {
            Toastr::info('This Post Is Already Approved :)','Info');
            }

            return  redirect()->back();
        }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function destroy(Post $post)
    {
        if (Storage::disk('public')->exists('post/'.$post->image)) 
        {
            Storage::disk('public')->delete('post/'.$post->image);
        }
        //Delete Post With Related Post
        $post->categories()->detach();
        $post->tags()->detach();
        $post->delete();
        Toastr::success('Post Sussessfully Deleted :)', 'Success');
        return  redirect()->back();
    }
}
