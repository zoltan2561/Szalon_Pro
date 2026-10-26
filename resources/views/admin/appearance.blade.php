@php($appearance = old('appearance', $brand))
<section class="panel" id="arculat"><p class="eyebrow">02 · ARCULAT</p><h2>A te stílusod.</h2>
    <input type="hidden" name="theme" value="{{ $salon->theme }}">
    <p class="settings-note">Jelenlegi kiindulópont: <strong>{{ $brand['label'] }}</strong>. Az alábbi beállításokat a lap alján lévő mentés rögzíti.</p>
    <div class="form-grid three">
        <label>Főoldali elrendezés<select name="appearance[layout]"><option value="split" @selected(($appearance['layout'] ?? 'split') === 'split')>Atelier · ívelt képes elrendezés</option><option value="immersive" @selected(($appearance['layout'] ?? '') === 'immersive')>Nagy képes nyitóoldal</option></select></label>
        <label>Betűkészlet<select name="font"><option value="serif" @selected(old('font',$salon->font)==='serif')>Playfair Display · elegáns</option><option value="sans" @selected(old('font',$salon->font)==='sans')>DM Sans · letisztult</option></select></label>
        <label>Sarkok<select name="appearance[radius]">@foreach(['4'=>'Finoman szögletes','8'=>'Visszafogott','16'=>'Lágy','24'=>'Kerekített'] as $value=>$label)<option value="{{ $value }}" @selected((string)($appearance['radius'] ?? '8') === (string)$value)>{{ $label }}</option>@endforeach</select></label>
    </div>
    <div class="appearance-colors">
        <label>Arculati szín<input type="color" name="accent" value="{{ old('accent',$salon->accent) }}" required></label>
        @foreach(['paper'=>'Oldal háttere','surface'=>'Kiemelt felületek','ink'=>'Fő szövegszín','muted'=>'Másodlagos szöveg','line'=>'Elválasztók'] as $key=>$label)<label>{{ $label }}<input type="color" name="appearance[{{ $key }}]" value="{{ $appearance[$key] }}" required></label>@endforeach
    </div>
    <p class="settings-note">A gombok feliratszíne az arculati színhez igazodik. A szöveg és a hátterek kontrasztját mentéskor ellenőrizzük.</p>
    <div class="form-grid"><label>Rövid felirat a fő üzenet fölött<input name="appearance[eyebrow]" maxlength="100" value="{{ $appearance['eyebrow'] }}" required></label><label>Foglalási gomb szövege<input name="appearance[cta_label]" maxlength="40" value="{{ $appearance['cta_label'] }}" required></label></div>
    <label class="check"><input type="checkbox" name="appearance[show_team]" value="1" @checked($appearance['show_team'] ?? false)><span>Szakemberek megjelenítése a főoldalon</span></label>
    <label class="check"><input type="checkbox" name="appearance[show_gallery]" value="1" @checked($appearance['show_gallery'] ?? false)><span>Referenciafotók megjelenítése a főoldalon</span></label>
    <hr><h3>Logó és képek</h3><p class="settings-note">{{ $salon->demo ? 'A bemutató profil generált hangulatképet használ.' : 'A nyilvános oldalon kizárólag a saját feltöltött fotód jelenik meg. Kép nélkül szöveges nyitóoldalt használunk.' }} Saját fotóval és logóval személyessé teheted az oldalt.</p>
    @if($brand['show_hero'])<img class="upload-preview" src="{{ $brand['hero_mobile'] }}" alt="Jelenlegi nyitókép" width="800" height="500" loading="lazy">@endif
    <label>Nyitókép (fekvő JPG, PNG, WebP; legfeljebb 4 MB)<input type="file" name="hero_image" accept="image/png,image/jpeg,image/webp"></label>
    @if($salon->hero_image)<label class="check"><input type="checkbox" name="reset_hero" value="1"><span>{{ $salon->demo ? 'A profil eredeti hangulatképét használom' : 'A saját nyitóképet eltávolítom' }}</span></label>@endif
    <label>Saját logó (JPG, PNG, WebP; legfeljebb 2 MB)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
    @if($salon->logo)<label class="check"><input type="checkbox" name="reset_logo" value="1"><span>A mellékelt SP-logót használom</span></label>@endif
    <label>Referenciafotók (legfeljebb 8 kép, képenként 4 MB)<input type="file" name="gallery[]" accept="image/png,image/jpeg,image/webp" multiple></label>
    <label class="check"><input type="checkbox" name="clear_gallery" value="1"><span>Korábbi referenciafotók eltávolítása a weboldalról</span></label>
    <label class="check"><input type="checkbox" name="content_rights" value="1" @checked(old('content_rights')) @required(!$salon->content_rights_accepted_at)><span>A megadott szövegek és képek felhasználására jogosult vagyok.</span></label>
@if($salon->content_rights_accepted_at)<p class="settings-note">A felhasználási jogokat korábban elfogadtad. Új képfeltöltésnél kérjük, erősítsd meg újra.</p>@endif
</section>
