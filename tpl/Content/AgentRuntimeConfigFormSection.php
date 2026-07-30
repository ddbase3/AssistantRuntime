<?php
$agentConfigForm = is_array($this->_['agent_config_form'] ?? null) ? $this->_['agent_config_form'] : [];
$formId = (string)($agentConfigForm['form_id'] ?? 'base3_agent_config');
$selectedRuntime = (string)($agentConfigForm['selected_runtime'] ?? '');
$showRuntimeSelector = !empty($agentConfigForm['show_runtime_selector']);
$runtimeActive = !array_key_exists('runtime_active', $agentConfigForm) || !empty($agentConfigForm['runtime_active']);
$runtimeOptions = is_array($agentConfigForm['runtime_options'] ?? null) ? $agentConfigForm['runtime_options'] : [];
$runtimeSections = is_array($agentConfigForm['runtime_sections'] ?? null) ? $agentConfigForm['runtime_sections'] : [];
$translations = is_array($agentConfigForm['translations'] ?? null) ? $agentConfigForm['translations'] : [];
$t = static function(string $key, string $fallback) use ($translations): string {
	$value = $translations[$key] ?? null;
	return is_scalar($value) && trim((string)$value) !== '' ? trim((string)$value) : $fallback;
};
$rootId = $formId . '_runtime_config';
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$selected = static fn($current, $value): string => (string)$current === (string)$value ? ' selected="selected"' : '';
$renderRuntimeTemplate = static function(string $template, array $form): void {
	$runtimeAgentConfigForm = $form;
	include $template;
};
?>
<style>
.base3-agent-runtime-root * { box-sizing:border-box; }
.base3-agent-runtime-selector { margin:0 0 18px; padding:16px; border:1px solid #cfd8e3; border-radius:6px; background:#f7f9fc; }
.base3-agent-runtime-row { display:grid; grid-template-columns:minmax(150px,220px) minmax(0,1fr); gap:8px 18px; }
.base3-agent-runtime-label { padding-top:7px; font-weight:600; }
.base3-agent-runtime-selector select { width:100%; max-width:760px; min-height:34px; padding:6px 8px; border:1px solid #bbb; border-radius:3px; background:#fff; font:inherit; }
.base3-agent-runtime-help { max-width:800px; margin:5px 0 0; color:#666; font-size:12px; line-height:1.4; }
.base3-agent-runtime-section { min-width:0; margin:0; padding:0; border:0; }
.base3-agent-runtime-section[hidden] { display:none !important; }
@media(max-width:700px){.base3-agent-runtime-row{display:block}.base3-agent-runtime-label{display:block;padding:0;margin:0 0 5px}}
</style>

<div id="<?php echo $e($rootId); ?>" class="base3-agent-runtime-root" data-base3-agent-config-root="1">
<?php if ($showRuntimeSelector) { ?>
	<div class="base3-agent-runtime-selector">
		<div class="base3-agent-runtime-row">
			<label class="base3-agent-runtime-label" for="<?php echo $e($formId); ?>_runtime"><?php echo $e($t('agent_runtime_label', 'Agent runtime')); ?></label>
			<div>
				<select id="<?php echo $e($formId); ?>_runtime" name="agent_runtime" data-agent-runtime-select="1">
<?php foreach ($runtimeOptions as $option) {
	$id = (string)($option['id'] ?? '');
	if ($id === '') {
		continue;
	}
?>
					<option value="<?php echo $e($id); ?>" data-description="<?php echo $e($option['description'] ?? ''); ?>"<?php echo $selected($selectedRuntime, $id); ?>><?php echo $e(($option['label'] ?? $id) . ' (' . $id . ')'); ?></option>
<?php } ?>
				</select>
				<p class="base3-agent-runtime-help" data-agent-runtime-description></p>
			</div>
		</div>
	</div>
<?php } else { ?>
	<input type="hidden" name="agent_runtime" value="<?php echo $e($runtimeActive ? $selectedRuntime : ''); ?>" data-agent-runtime-hidden="1" />
<?php } ?>

<?php foreach ($runtimeSections as $section) {
	$id = (string)($section['id'] ?? '');
	$template = (string)($section['template'] ?? '');
	$runtimeForm = is_array($section['form'] ?? null) ? $section['form'] : [];
	if ($id === '' || $template === '' || !is_file($template)) {
		continue;
	}
	$isActive = $runtimeActive && $id === $selectedRuntime;
?>
	<fieldset class="base3-agent-runtime-section" data-agent-runtime-section="<?php echo $e($id); ?>"<?php echo $isActive ? '' : ' hidden disabled'; ?>>
<?php $renderRuntimeTemplate($template, $runtimeForm); ?>
	</fieldset>
<?php } ?>
</div>

<script>
(function(){
	var root=document.getElementById(<?php echo json_encode($rootId); ?>);if(!root||root.dataset.ready==='1')return;root.dataset.ready='1';
	var selector=root.querySelector('[data-agent-runtime-select]');
	var hidden=root.querySelector('[data-agent-runtime-hidden]');
	var description=root.querySelector('[data-agent-runtime-description]');
	var active=<?php echo $runtimeActive ? 'true' : 'false'; ?>;
	var selectedRuntime=<?php echo json_encode($selectedRuntime); ?>;
	function normalize(id){return String(id||'').toLowerCase().replace(/[^a-z0-9._-]+/g,'');}
	function currentRuntime(){return selector?normalize(selector.value):normalize(selectedRuntime);}
	function runtimeSection(id){return root.querySelector('[data-agent-runtime-section="'+normalize(id).replace(/"/g,'\\"')+'"]');}
	function runtimeRoot(id){var section=runtimeSection(id);return section?section.querySelector('[data-base3-agent-runtime-config-root]'):null;}
	function updateVisibility(){
		var id=currentRuntime();
		root.querySelectorAll('[data-agent-runtime-section]').forEach(function(section){
			var sectionId=normalize(section.dataset.agentRuntimeSection||'');
			var enabled=active&&sectionId===id;
			section.hidden=!enabled;
			section.disabled=!enabled;
		});
		if(hidden)hidden.value=active?id:'';
		if(description&&selector){var option=selector.options[selector.selectedIndex];description.textContent=option?String(option.dataset.description||''):'';}
	}
	if(selector)selector.addEventListener('change',function(){selectedRuntime=currentRuntime();active=true;updateVisibility();});
	root.__base3AgentConfigSelectRuntime=function(id,isActive){
		id=normalize(id);active=isActive!==false&&id!=='';
		if(id!=='')selectedRuntime=id;
		if(selector&&id!=='')selector.value=id;
		updateVisibility();
	};
	root.__base3AgentConfigUpdateValues=function(values){
		values=values&&typeof values==='object'?values:{};
		var id=normalize(values.agent_runtime||currentRuntime());
		if(id!=='')selectedRuntime=id;
		if(selector&&id!=='')selector.value=id;
		updateVisibility();
		root.querySelectorAll('[data-agent-runtime-section]').forEach(function(section){
			var runtime=section.querySelector('[data-base3-agent-runtime-config-root]');
			if(runtime&&typeof runtime.__base3AgentRuntimeConfigUpdateValues==='function'){
				runtime.__base3AgentRuntimeConfigUpdateValues(values);
			}
		});
	};
	root.__base3AgentConfigPrepareSubmit=function(){
		updateVisibility();
		if(!active)return true;
		var runtime=runtimeRoot(currentRuntime());
		if(runtime&&typeof runtime.__base3AgentRuntimeConfigPrepareSubmit==='function'){
			return runtime.__base3AgentRuntimeConfigPrepareSubmit();
		}
		return true;
	};
	updateVisibility();
})();
</script>
