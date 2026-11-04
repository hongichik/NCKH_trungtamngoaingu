<?php
namespace Tests\Feature;
use App\Models\{User, MenuItem, Page};
use Tests\TestCase;
class MenuTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    public function test_admin_can_manage_menu_and_public_site_renders_it(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $u = User::forceCreate(["name"=>"a","email"=>"a@a.a","password"=>bcrypt("x"),"is_admin"=>1]);
        $p = Page::create(["title"=>"Giới thiệu","slug"=>"gioi-thieu","status"=>"published"]);
        $this->assertSame(11, MenuItem::count());
        $this->actingAs($u)->get("/admin/menus")->assertOk()->assertSee("Đăng ký thi HSK");
        $this->get("/admin/menus?location=footer_support")->assertOk()->assertSee("Tra cứu chứng chỉ");
        $this->get("/admin/menus/create?location=header")->assertOk();
        $this->post("/admin/menus",["title"=>"Giới thiệu","location"=>"header","link_type"=>"url","url_value"=>"#","is_active"=>1])->assertRedirect()->assertSessionHasNoErrors();
        $parent = MenuItem::latest("id")->first();
        $this->post("/admin/menus",["title"=>"Về chúng tôi","location"=>"header","parent_id"=>$parent->id,"link_type"=>"page","page_value"=>$p->id,"is_active"=>1,"open_new_tab"=>1])->assertSessionHasNoErrors();
        $child = MenuItem::latest("id")->first();
        $this->assertSame($parent->id, $child->parent_id);
        $this->get("/admin/menus/{$child->id}/edit")->assertOk();
        // validation: missing target
        $this->post("/admin/menus",["title"=>"x","location"=>"header","link_type"=>"page"])->assertSessionHasErrors("page_value");
        // parent with children can't become child
        $other = MenuItem::where("location","header")->whereNull("parent_id")->where("id","!=",$parent->id)->first();
        $this->put("/admin/menus/{$parent->id}",["title"=>"Giới thiệu","location"=>"header","parent_id"=>$other->id,"link_type"=>"url","url_value"=>"#","is_active"=>1])->assertSessionHasErrors("parent_id");
        // reorder: reverse top-level
        $ids = MenuItem::where("location","header")->whereNull("parent_id")->orderByDesc("order")->pluck("id");
        $items = $ids->map(fn($i)=>["id"=>$i,"parent_id"=>null])->all();
        $items[] = ["id"=>$child->id,"parent_id"=>$parent->id];
        $this->postJson("/admin/menus/reorder",["location"=>"header","items"=>$items])->assertOk();
        $this->assertSame("Giới thiệu", MenuItem::where("location","header")->whereNull("parent_id")->orderBy("order")->first()->title);
        // reorder with item from another location rejected
        $foot = MenuItem::where("location","footer_support")->first();
        $this->postJson("/admin/menus/reorder",["location"=>"header","items"=>[["id"=>$foot->id]]])->assertStatus(422);
        // hide/show
        $this->post("/admin/menus/{$other->id}/toggle"); $this->assertFalse($other->fresh()->is_active);
        auth()->logout();
        $html = $this->get("/lien-he")->assertOk()->getContent();
        preg_match('~<ul class="navbar-nav ms-auto">(.*?)</ul>\s*</div>~s',$html,$m);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(url('/gioi-thieu'), '#').'"\s+target="_blank"#', $html);
        $this->assertStringNotContainsString(">{$other->title}<", $m[1]);
        $this->assertStringContainsString("Thư viện ảnh", $html);
        $this->get("/dang-ky-chung-chi?type=cntt")->assertSee('nav-link active" href="'.route('portal.auth',['type'=>'cntt']).'"', false);
        // page slug change refreshes menu
        $p->update(["slug"=>"gioi-thieu-moi"]);
        $this->get("/lien-he")->assertSee(url('/gioi-thieu-moi'));
        $this->actingAs($u)->delete("/admin/menus/{$parent->id}");
        $this->assertNull(MenuItem::find($child->id));
    }
}
