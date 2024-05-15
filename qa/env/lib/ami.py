import yaml

import shell
import aws



def generate(name):
	vars = {}
	with open("./amis/" + name + "/vars.yml", 'r') as stream:
		vars = yaml.load(stream)

	ami_name = 'w3tcqa-' + name
	box_instance_name = 'w3tcqa-amisource-' + name

	aws.ami_delete(ami_name)
	ec2 = aws.ec2_start(vars['W3D_AWS_AMI'], box_instance_name)
	ip = ec2['ip']

	aws.ec2_wait_ready(ip)
	init(name, ip)

	aws.ami_create(ec2['aws_instance_id'], ami_name)

	# box should be left running, otherwise AWS starts well,
	# but fails to build AMI
	# aws.ec2_stop(ec2['aws_instance_id'])



def init(name, ip):
	init_ip = 'ubuntu@' + ip

	# remove warning "sudo: unable to resolve host ip-..."
	shell.ssh(init_ip,
		'echo "echo \"127.0.0.1 $(cat /etc/hostname)\" >>/etc/hosts" >~/fix-hosts && ' +
		'chmod a+x ~/fix-hosts && ' +
		'sudo ~/fix-hosts && ' +
		'sudo cp /home/ubuntu/.ssh/authorized_keys /root/.ssh/authorized_keys')

	root_ip = 'root@' + ip
	shell.scp( \
		"", "./amis/" + name + "/export.sh", \
		root_ip, "/root/ami-environment.sh")

	shell.ssh(root_ip,
		'echo "LANG=C.UTF-8"  >>/etc/environment && ' +
		'echo "LC_ALL=C.UTF-8" >>/etc/environment && ' +
		'cat /root/ami-environment.sh >>/etc/environment && ' +
		'mkdir -p /share')

	shell.scp( \
		'', './scripts', \
		root_ip, '/share/scripts')

	print 'make init scripts executable'

	shell.ssh(root_ip, 'chmod 755 /share/scripts/*.sh')

	shell.ssh(root_ip, '/share/scripts/init-image/100-init.sh')
	shell.ssh(root_ip, '/share/scripts/init-image/200-db.sh')
	shell.ssh(root_ip, '/share/scripts/init-image/300-php.sh')
	shell.ssh(root_ip, '/share/scripts/init-image/400-http-server.sh')
	shell.ssh(root_ip, '/share/scripts/init-image/500-varnish.sh')
	shell.ssh(root_ip, '/share/scripts/init-image/600-wp-cli.sh')
