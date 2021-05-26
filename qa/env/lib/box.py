import yaml

import shell
import aws



def start(box_name, box_instance_name):
	vars = {}

	with open("./boxes/" + box_name + "/vars.yml", 'r') as stream:
		vars = yaml.load(stream)

	ami_name = 'w3tcqa-' + vars['W3D_AWS_AMI_NAME']
	ami_id = aws.ami_name2id(ami_name)

	return aws.ec2_start(ami_id, box_instance_name)



def init(ip, box_name, box_instance_name):
	vars = {}
	with open("./working/w3tc_zip_url.yml", 'r') as stream:
		vars = yaml.load(stream)

	init_ip = 'ubuntu@' + ip

	# remove warning "sudo: unable to resolve host ip-..."
	shell.ssh(init_ip,
		'echo "echo \"127.0.0.1 $(cat /etc/hostname)\" >>/etc/hosts" >~/fix-hosts && ' +
		'chmod a+x ~/fix-hosts && ' +
		'sudo ~/fix-hosts && ' +
		'sudo cp /home/ubuntu/.ssh/authorized_keys /root/.ssh/authorized_keys')

	root_ip = 'root@' + ip
	shell.scp( \
		"", "./boxes/" + box_name + "/export.sh", \
		root_ip, "/root/box-environment.sh")

	shell.ssh(root_ip,
		'echo "LANG=C.UTF-8"  >>/etc/environment && ' +
		'echo "LC_ALL=C.UTF-8" >>/etc/environment && ' +
		'echo "W3D_INSTANCE_ID=\"' + box_instance_name + '\"" >>/etc/environment && ' +
		'cat /root/box-environment.sh >>/etc/environment && ' +
		'mkdir -p /share/scripts && ' +
		'mkdir -p /share/environments')

	shell.scp( \
		'', './boxes/' + box_name + '/environments/*', \
		root_ip, '/share/environments')

	# dev only - upload recently modified scripts
#	shell.scp( \
#		'', './scripts/w3test', \
#		root_ip, '/share/scripts/w3test')

	shell.ssh(root_ip, 'cd /share && wget ' + vars['W3D_W3TC_ZIP_URL'] + ' -q -O ./w3tc.zip')
	shell.ssh(root_ip, 'cd /share && unzip -q /share/w3tc.zip')

	shell.ssh(root_ip, '/share/scripts/init-box/400-http-server.sh')
	shell.ssh(root_ip, '/share/scripts/init-box/600-wp-cli.sh')
	shell.ssh(root_ip, '/share/scripts/init-box/700-wordpress.sh')
	shell.ssh(root_ip, '/share/scripts/init-box/800-w3tc.sh')
