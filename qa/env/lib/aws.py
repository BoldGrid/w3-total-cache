import os
import time
import socket

import shell



def ami_create(instance_id, ami_name):
	shell.shell_json(['aws', 'ec2', 'create-image', '--instance-id',
		instance_id, '--name', ami_name])



def ami_delete(ami_name):
	v = shell.shell_json(['aws', 'ec2', 'describe-images', '--owners', 'self', '--filters',
		'Name=name,Values=' + ami_name])

	if (len(v['Images']) <= 0):
		print 'existing AMI ' + ami_name + ' not found'
	else:
		print 'deleting AMI ' + ami_name

		ami_id = v['Images'][0]['ImageId']
		snapshot_id = v['Images'][0]['BlockDeviceMappings'][0]['Ebs']['SnapshotId']
		print ami_id
		print snapshot_id

		shell.shell(['aws', 'ec2', 'deregister-image', '--image-id', ami_id])
		shell.shell(['aws', 'ec2', 'delete-snapshot', '--snapshot-id', snapshot_id])



def ami_name2id(ami_name):
	v = shell.shell_json(['aws', 'ec2', 'describe-images', '--owners', 'self', '--filters',
		'Name=name,Values=' + ami_name])
	ami_id = v['Images'][0]['ImageId']

	return ami_id



def ec2_list_aws_instance_ids():
	v = shell.shell_json(['aws', 'ec2', 'describe-instances',
		'--filters', 'Name=tag:Type,Values=w3tcqa-box',
		'--filters', 'Name=instance-state-code,Values=0,16',
		'--query', 'Reservations[].Instances[].InstanceId'])
	return v



def ec2_start(ami_id, box_instance_name):
	print 'start instance ' + box_instance_name

	v = shell.shell_json(['aws', 'ec2', 'run-instances',
		'--count', '1',
		# too small instance cause random failures with errors like:
		# mysql connection failed, http 500, file not found
		'--instance-type', os.environ['W3TCQA_EC2_INSTANCE_TYPE'],
		'--image-id', ami_id,
		'--key-name', os.environ['W3TCQA_EC2_KEY_NAME'],
		# Dont use default secuirty group with Allow All, since it effectively
		# doesnt allow anything
		'--security-group-ids', os.environ['W3TCQA_EC2_SECURITY_GROUP_ID']])
	aws_instance_id = v['Instances'][0]['InstanceId']

	print 'set tag'
	shell.shell(['aws', 'ec2', 'create-tags',
		'--resources', aws_instance_id,
		'--tags', 'Key=Type,Value=w3tcqa-box'])
	shell.shell(['aws', 'ec2', 'create-tags',
		'--resources', aws_instance_id,
		'--tags', 'Key=Name,Value=' + box_instance_name])

	print 'get ip'
	for i in range(50):
		try:
			v = shell.shell_json(['aws', 'ec2', 'describe-instances',
				'--filters', 'Name=instance-id,Values=' + aws_instance_id,
				'--query', 'Reservations[].Instances[].PublicIpAddress'])
			ip = v[0]

			print('ip is available: ' + ip)
			return {
				'ip': ip,
				'aws_instance_id': aws_instance_id
			}
		except:
			print('ip is not available yet')

	raise Exception('ip not available')






def ec2_stop(aws_instance_id):
	shell.shell(['aws', 'ec2', 'terminate-instances', '--instance-ids', aws_instance_id])



def ec2_wait_ready(ip):
	ssh_wait_port(ip)
	ssh_wait_functional(ip)



def ssh_wait_port(ip):
	for i in range(50):
		s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
		s.settimeout(1)
		try:
			s.connect((ip, 22))
			s.shutdown(socket.SHUT_RDWR)
			print('port is open')
			return True
		except:
			print('port not ready')
		finally:
			s.close()

		time.sleep(5)

	raise Exception('port is still not open')



def ssh_wait_functional(ip):
	for i in range(50):
		try:
			v = shell.ssh('ubuntu@' + ip, 'echo "working"')
			if "working" not in v:
				raise Exception('ssh doesnt work')

			return True
		except:
			print('ssh doesnt work')

		time.sleep(5)
