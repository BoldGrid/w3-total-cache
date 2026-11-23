import json
import os
import subprocess
import sys



def _ssh_cmd(host, cmd):
	return [
		'timeout', '600',
		'ssh', host,
		'-i', os.path.expanduser('~/.ssh/key-aws-w3tcqa.pem'),
		'-o', 'StrictHostKeyChecking=no',
		'-o', 'UserKnownHostsFile=./working/hosts',
		cmd]



def ssh_exec(host, cmd):
	print host + ': ' + cmd

	proc = subprocess.Popen(
		_ssh_cmd(host, cmd),
		stdout=subprocess.PIPE,
		stderr=subprocess.STDOUT)
	stdout, _ = proc.communicate()
	output = stdout or ''

	if output != '':
		print output

	return proc.returncode, output



def ssh(host, cmd):
	ret, output = ssh_exec(host, cmd)
	return output



def ssh_assert(host, cmd):
	ret, output = ssh_exec(host, cmd)
	if ret != 0:
		raise Exception('failed to execute on ' + host + ': ' + cmd)
	return output



def scp(host_src, src, host_dst, dst):
	# popen doesnt work here since escape filemasks
	if len(host_src) > 0:
		host_src = host_src + ':'
	if len(host_dst) > 0:
		host_dst = host_dst + ':'

	v = shell2('scp ' +
		'-i ~/.ssh/key-aws-w3tcqa.pem ' +
		'-o StrictHostKeyChecking=no ' +
		'-o UserKnownHostsFile=./working/hosts -r ' +
		host_src + src + ' ' + host_dst + dst)
	print v
	return v



def shell(cmd):
	print ' '.join(cmd)
	return shell_silent(cmd)



def shell_json(cmd):
	print ' ' . join(cmd)
	v = shell_silent(cmd)
	try:
		v = json.loads(v)
		return v
	except BaseException as e:
		print('Failed to execute ' + ' ' . join(cmd))
		print('output:')
		print(v)
		print('exception:')
		print(str(e))

		sys.stderr.write('Failed to execute ' + ' ' . join(cmd) + "\n")
		sys.stderr.write(v + "\n")

	return {}



def shell_silent(cmd):
	out = subprocess.Popen(cmd,
		stdout=subprocess.PIPE,
		stderr=subprocess.STDOUT)
	stdout,stderr = out.communicate()

	if stderr != None:
		print "Error: "
		print stderr

	return stdout



def shell2(cmd):
	v = os.system(cmd)
	if v > 0:
		print("failed " + cmd)

	return v
