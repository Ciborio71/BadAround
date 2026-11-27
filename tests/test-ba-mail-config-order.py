import importlib.util
from pathlib import Path
spec=importlib.util.spec_from_file_location('patch',Path(__file__).resolve().parents[1]/'tools/ba_identity_mail_config_order.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
base="<?php\nrequire_once ABSPATH . 'wp-settings.php';\n"
definitions="\n".join("define( '"+name+"', 'fixture-value' );" for name in module.NAMES)+"\n"
original=(base+definitions).encode()
candidate=module.patch(original)
assert all(candidate.index(name.encode())<candidate.index(b'wp-settings.php') for name in module.NAMES)
assert all(candidate.count(name.encode())==1 for name in module.NAMES)
assert sorted(original.splitlines())==sorted(line for line in candidate.splitlines() if line and not line.startswith(b'// BA_IDENTITY_MAIL_CONFIG_ORDER_V1'))
for bad in [base.encode(),original+definitions.encode(),definitions.encode()+base.encode(),candidate]:
    try:module.patch(bad)
    except RuntimeError:pass
    else:raise AssertionError('Malformed or duplicate configuration was accepted')
module.lint(candidate)
print('MAIL_CONFIG_ORDER_TESTS_PASS')
